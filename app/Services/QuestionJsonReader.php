<?php

namespace App\Services;

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\Question;
use App\Models\QuestionStimulus;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Reads pasted question JSON — the quick way in for questions drafted with
 * an AI tool — and decides whether it is acceptable: the one place the
 * format is defined, checked and explained (the guide and sample offered to
 * copy). It builds nothing; each importer turns what it reads into its own
 * questions (QuestionJsonImporter for a chapter of the question bank,
 * PastPaperJsonImporter for a past paper).
 *
 * How it reads depends on where the questions go: a past paper's MCQ list
 * takes only MCQs and its CQ list only CQs (`$onlyType`, so "type" may be
 * left out), and a past paper's CQs carry optional answers
 * (`$withCqAnswers`) but no difficulty (`$withDifficulty`). Every MCQ may
 * carry an explanation, wherever it goes.
 *
 * It is all or nothing — one unacceptable question and the whole JSON is
 * refused with every problem listed, so the user fixes it and pastes again.
 */
final class QuestionJsonReader
{
    /**
     * The field of an import form the problems are reported against.
     */
    public const ERROR_KEY = 'json';

    public function __construct(
        private readonly ?QuestionType $onlyType = null,
        private readonly bool $withCqAnswers = false,
        private readonly bool $withDifficulty = true,
    ) {}

    /**
     * Every question of the JSON, read and normalized — the text already
     * as stored (HTML, formulas as KaTeX embeds):
     * `type`, `question`, `image`, `difficulty` (null when not given), and
     * for an MCQ `options` (each `option`/`image`/`is_correct`), `marks`
     * (null when not given) and `explanation`, for a CQ `parts` (each
     * `part_type`, `part_order`, `part_text`, `part_image`, `marks` and
     * `answer`). MCQs sharing a stimulus come as one entry: `stimulus` (its
     * text), `stimulus_image` and `questions` (those MCQs, read the same
     * way). An image is still the file name the JSON gave — which image
     * that is, is ImportedImages' to say.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ValidationException listing every problem found
     */
    public function read(string $json): array
    {
        $items = $this->decode($json);
        $max = BillingSetting::current()->question_import_max;
        $count = array_sum(array_map(fn (mixed $item): int => $this->isGroup($item) && is_array($item['questions'] ?? null) ? count($item['questions']) : 1, $items));

        if ($count > $max) {
            $this->fail([__('There are :count questions here, but at most :max can be added at once.', ['count' => $count, 'max' => $max])]);
        }

        $problems = [];
        $questions = [];

        foreach ($items as $index => $item) {
            $itemProblems = $this->problemsWith($item);

            foreach ($itemProblems as $problem) {
                $problems[] = __('Question :number: :problem', ['number' => $index + 1, 'problem' => $problem]);
            }

            if ($itemProblems === []) {
                $questions[] = $this->normalize($item);
            }
        }

        if ($problems !== []) {
            $this->fail($problems);
        }

        return $questions;
    }

    /**
     * What a user copies and hands to an AI tool to have questions written
     * in the format: the rules in words, then the example. The rules are
     * stated from the same limits the reading checks against.
     */
    public function formatGuide(): string
    {
        $rules = array_values(array_filter([
            __('Write the questions as a JSON list in exactly the format below, and reply with the JSON only.'),
            match (true) {
                $this->onlyType === QuestionType::Mcq => __('Every question is an MCQ.'),
                $this->onlyType === QuestionType::Cq => __('Every question is a CQ (creative question).'),
                default => __('"type" is "mcq" or "cq". "difficulty" is one of: :values.', ['values' => implode(', ', array_column(Difficulty::cases(), 'value'))]),
            },
            $this->onlyType !== QuestionType::Cq
                ? __('MCQ: "options" has :min to :max options, and "answer" is the number of the correct one (1 = the first option). "marks" may be left out.', ['min' => Question::MIN_MCQ_OPTIONS, 'max' => Question::MAX_MCQ_OPTIONS])
                : null,
            $this->onlyType !== QuestionType::Mcq
                ? __('CQ: "question" is the stimulus, and "parts" has exactly :count sub-questions in this order — :order — each with its "text" and "marks".', [
                    'count' => count(CqPartType::ordered()),
                    'order' => implode(', ', array_map(fn (CqPartType $type): string => $type->getLabel(), CqPartType::ordered())),
                ])
                : null,
            $this->onlyType !== QuestionType::Cq
                ? __('"explanation" (optional — leave it out or empty when there is none): why the answer is the right one.')
                : null,
            $this->onlyType !== QuestionType::Cq
                ? __('MCQs that share a stimulus go in one object: "stimulus" is its text, and "questions" lists :min to :max MCQs. Do not write "answer questions 42 and 43" — the paper numbers them itself.', ['min' => QuestionStimulus::MIN_QUESTIONS, 'max' => QuestionStimulus::MAX_QUESTIONS])
                : null,
            $this->withCqAnswers && $this->onlyType !== QuestionType::Mcq
                ? __('"answer" on a sub-question (may be left out): its answer.')
                : null,
            __('Maths: write every formula as LaTeX between $ signs, write every backslash twice (:twice, not \frac), and keep Bangla text outside the $ signs.', ['twice' => '\\\\frac']),
            __('Images: where a question, a stimulus, a CQ sub-question or an option has a figure (a diagram, graph, circuit, or a table drawn as a picture), crop that figure into an image file of its own and give its file name in "image" — beside the text of a question, a stimulus or a sub-question; an option with a figure is written as an object, {"text": "…", "image": "…"}, its "text" "" when the figure is all of it.'),
            __('Name the image files with English letters, digits and hyphens only, ending in .png, .jpg or .webp, every name different: q12.png for question 12, q12-c.png for its option C, q12-part2.png for its second sub-question, s3.png for the stimulus of question 3.'),
            __('Do not describe a figure in the text or put it inside the text. Give all the image files together in one ZIP, without folders, named exactly as in the JSON.'),
            __('Plain text only — no HTML, no Markdown.'),
        ]));

        return collect($rules)->map(fn (string $rule): string => "- {$rule}")->implode("\n")."\n\n".$this->sampleJson();
    }

    /**
     * The example of the format: an MCQ and a CQ, or just the one kind
     * this list takes — with figures as image files.
     */
    public function sampleJson(): string
    {
        $mcq = array_filter([
            'type' => $this->onlyType ? null : QuestionType::Mcq->value,
            'question' => '$x^2 = 9$ হলে $x$ এর ধনাত্মক মান কত?',
            'options' => ['$2$', '$3$', '$\frac{9}{2}$', '$9$'],
            'answer' => 2,
            'difficulty' => $this->withDifficulty ? Difficulty::Easy->value : null,
            'explanation' => '$3^2 = 9$, তাই $x = 3$।',
        ], fn (mixed $value): bool => $value !== null);

        $cq = array_filter([
            'type' => $this->onlyType ? null : QuestionType::Cq->value,
            'question' => 'একটি আয়তক্ষেত্রের দৈর্ঘ্য $x$ মিটার এবং প্রস্থ $y$ মিটার।',
            'parts' => [
                ['text' => 'আয়তক্ষেত্র কাকে বলে?', 'marks' => 1, 'answer' => 'যে চতুর্ভুজের প্রতিটি কোণ সমকোণ।'],
                ['text' => 'ক্ষেত্রফলের সূত্রটি ব্যাখ্যা করো।', 'marks' => 2, 'answer' => null],
                ['text' => '$x = 5$ ও $y = 3$ হলে পরিসীমা নির্ণয় করো।', 'marks' => 3, 'answer' => '$2(5 + 3) = 16$ মিটার।'],
                ['text' => 'দৈর্ঘ্য দ্বিগুণ করলে ক্ষেত্রফলের পরিবর্তন বিশ্লেষণ করো।', 'marks' => 4, 'answer' => null],
            ],
            'difficulty' => $this->withDifficulty ? Difficulty::Medium->value : null,
        ], fn (mixed $value): bool => $value !== null);

        // Only a past paper's CQ has answers, and only some of them.
        $cq['parts'] = array_map(
            fn (array $part): array => array_filter($part, fn (mixed $value, string $key): bool => $key !== 'answer' || ($this->withCqAnswers && $value !== null), ARRAY_FILTER_USE_BOTH),
            $cq['parts'],
        );

        $group = [
            'stimulus' => 'একটি বর্তনীতে $4\\Omega$ ও $4\\Omega$ রোধ সমান্তরালে এবং $20$ V কোষের সাথে যুক্ত।',
            'questions' => [
                array_filter([
                    'question' => 'বর্তনীর তুল্যরোধ কত?',
                    'options' => ['$1\\Omega$', '$2\\Omega$', '$4\\Omega$', '$8\\Omega$'],
                    'answer' => 2,
                    'difficulty' => $this->withDifficulty ? Difficulty::Easy->value : null,
                ], fn (mixed $value): bool => $value !== null),
                array_filter([
                    'question' => 'বর্তনীর প্রবাহমাত্রা কত?',
                    'options' => ['$2.5$ A', '$5$ A', '$10$ A', '$20$ A'],
                    'answer' => 3,
                    'difficulty' => $this->withDifficulty ? Difficulty::Medium->value : null,
                    'explanation' => '$I = \\frac{20}{2} = 10$ A।',
                ], fn (mixed $value): bool => $value !== null),
            ],
        ];

        $sample = match ($this->onlyType) {
            QuestionType::Mcq => [$mcq, $group],
            QuestionType::Cq => [$cq],
            default => [$mcq, $cq, $group],
        };

        // Figures, named by where they stand in the sample as the guide
        // says: an MCQ with one in its question and one as an option — or,
        // where only CQs go, one in a sub-question.
        if ($this->onlyType === QuestionType::Cq) {
            $sample[0]['parts'][2]['image'] = 'q1-part3.png';
        } else {
            $number = count($sample) + 1;
            $sample[] = array_filter([
                'type' => $this->onlyType ? null : QuestionType::Mcq->value,
                'question' => 'চিত্রের বর্তনীর তুল্যরোধ কত?',
                'image' => "q{$number}.png",
                'options' => ['$2\\Omega$', '$4\\Omega$', ['text' => '', 'image' => "q{$number}-c.png"], '$8\\Omega$'],
                'answer' => 1,
                'difficulty' => $this->withDifficulty ? Difficulty::Easy->value : null,
            ], fn (mixed $value): bool => $value !== null);
        }

        return json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, mixed>
     */
    private function decode(string $json): array
    {
        // AI tools like to wrap their answer in a ```json code fence.
        $json = trim(preg_replace('/^\s*```[a-zA-Z]*\s*|\s*```\s*$/', '', $json) ?? $json);

        try {
            $decoded = json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->fail([__('This is not valid JSON (:reason). If it contains formulas, check that every backslash is written twice (:twice, not \frac).', ['reason' => $exception->getMessage(), 'twice' => '\\\\frac'])]);
        }

        if (is_array($decoded) && is_array($decoded['questions'] ?? null)) {
            $decoded = $decoded['questions'];
        }

        if (! is_array($decoded) || ! array_is_list($decoded) || $decoded === []) {
            $this->fail([__('The JSON must be a list of questions: [ { … }, { … } ].')]);
        }

        return $decoded;
    }

    /**
     * What is wrong with one question of the JSON — empty when nothing is.
     *
     * @return array<int, string>
     */
    private function problemsWith(mixed $item): array
    {
        if (! is_array($item) || array_is_list($item)) {
            return [__('it must be an object: { "question": … }')];
        }

        if ($this->isGroup($item)) {
            return $this->groupProblems($item);
        }

        $type = $this->typeOf($item);

        if (! $type) {
            return [__('"type" must be "mcq" or "cq"')];
        }

        if ($this->onlyType && $type !== $this->onlyType) {
            return [$this->onlyType === QuestionType::Mcq
                ? __('this list takes MCQs only — add CQs in the CQ list')
                : __('this list takes CQs only — add MCQs in the MCQ list')];
        }

        $problems = [
            ...$this->textProblems($item['question'] ?? null, __('the question text')),
            ...$this->imageProblems($item, __('the question')),
        ];

        if ($this->withDifficulty && isset($item['difficulty']) && ! $this->difficultyOf($item)) {
            $problems[] = __('"difficulty" must be one of: :values', ['values' => implode(', ', array_column(Difficulty::cases(), 'value'))]);
        }

        return [
            ...$problems,
            ...($type === QuestionType::Mcq ? $this->mcqProblems($item) : $this->cqProblems($item)),
        ];
    }

    /**
     * MCQs sharing a stimulus: the stimulus's text, and two to six MCQs —
     * each checked as any MCQ is, and named by its place under the stimulus.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function groupProblems(array $item): array
    {
        if ($this->onlyType === QuestionType::Cq) {
            return [__('this list takes CQs only — add MCQs in the MCQ list')];
        }

        $problems = [
            ...$this->textProblems($item['stimulus'], __('the stimulus')),
            ...$this->imageProblems($item, __('the stimulus')),
        ];
        $questions = $item['questions'] ?? null;

        if (! is_array($questions) || ! array_is_list($questions)
            || count($questions) < QuestionStimulus::MIN_QUESTIONS || count($questions) > QuestionStimulus::MAX_QUESTIONS) {
            return [...$problems, __('"questions" of a stimulus must be a list of :min to :max MCQs', ['min' => QuestionStimulus::MIN_QUESTIONS, 'max' => QuestionStimulus::MAX_QUESTIONS])];
        }

        foreach ($questions as $index => $question) {
            $questionProblems = ! is_array($question) || array_is_list($question)
                ? [__('it must be an object: { "question": … }')]
                : ($this->typeOf($question) !== QuestionType::Mcq || isset($question['parts'])
                    ? [__('only MCQs can share a stimulus')]
                    : [
                        ...$this->textProblems($question['question'] ?? null, __('the question text')),
                        ...$this->imageProblems($question, __('the question')),
                        ...($this->withDifficulty && isset($question['difficulty']) && ! $this->difficultyOf($question)
                            ? [__('"difficulty" must be one of: :values', ['values' => implode(', ', array_column(Difficulty::cases(), 'value'))])]
                            : []),
                        ...$this->mcqProblems($question),
                    ]);

            foreach ($questionProblems as $problem) {
                $problems[] = __('question :number of the stimulus: :problem', ['number' => $index + 1, 'problem' => $problem]);
            }
        }

        return $problems;
    }

    /**
     * An entry of MCQs sharing a stimulus, rather than one question.
     */
    private function isGroup(mixed $item): bool
    {
        return is_array($item) && ! array_is_list($item) && array_key_exists('stimulus', $item);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function mcqProblems(array $item): array
    {
        $options = $item['options'] ?? null;

        if (! is_array($options) || ! array_is_list($options)
            || count($options) < Question::MIN_MCQ_OPTIONS || count($options) > Question::MAX_MCQ_OPTIONS) {
            return [__('"options" must be a list of :min to :max options', ['min' => Question::MIN_MCQ_OPTIONS, 'max' => Question::MAX_MCQ_OPTIONS])];
        }

        $problems = [];

        foreach ($options as $index => $option) {
            $name = __('option :number', ['number' => $index + 1]);

            // An option with a figure is an object; its text may be left
            // empty when the figure is all there is to it.
            if (is_array($option) && ! array_is_list($option)) {
                $text = $option['text'] ?? '';
                array_push($problems, ...$this->imageProblems($option, $name));

                if (! is_string($text) || (trim($text) === '' && blank($option['image'] ?? null))) {
                    $problems[] = __(':name needs its "text", or an "image"', ['name' => $name]);
                } elseif (trim($text) !== '') {
                    array_push($problems, ...$this->textProblems($text, $name));
                }

                continue;
            }

            array_push($problems, ...$this->textProblems($option, $name));
        }

        $answer = $item['answer'] ?? null;

        if (filter_var($answer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => count($options)]]) === false) {
            $problems[] = __('"answer" must be the number of the correct option, from 1 to :max', ['max' => count($options)]);
        }

        if (isset($item['marks']) && ! $this->isValidMarks($item['marks'])) {
            $problems[] = $this->marksProblem(__('the question'));
        }

        // Optional: left out, null or empty all mean there is none.
        if (filled($item['explanation'] ?? null)) {
            array_push($problems, ...$this->textProblems($item['explanation'], __('the explanation')));
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function cqProblems(array $item): array
    {
        $parts = $item['parts'] ?? null;
        $expected = count(CqPartType::ordered());

        if (! is_array($parts) || ! array_is_list($parts) || count($parts) !== $expected) {
            return [__('"parts" must be a list of exactly :count sub-questions', ['count' => $expected])];
        }

        $problems = [];

        foreach ($parts as $index => $part) {
            $name = __('sub-question :number', ['number' => $index + 1]);

            if (! is_array($part)) {
                $problems[] = __(':name must be an object: { "text": …, "marks": … }', ['name' => $name]);

                continue;
            }

            array_push($problems, ...$this->textProblems($part['text'] ?? null, $name));
            array_push($problems, ...$this->imageProblems($part, $name));

            // No default here: how many marks each part carries is the
            // author's call, not something the code assumes.
            if (! $this->isValidMarks($part['marks'] ?? null)) {
                $problems[] = $this->marksProblem($name);
            }

            if ($this->withCqAnswers && filled($part['answer'] ?? null)) {
                array_push($problems, ...$this->textProblems($part['answer'], __('the answer of :name', ['name' => $name])));
            }
        }

        return $problems;
    }

    /**
     * @return array<int, string>
     */
    private function textProblems(mixed $text, string $name): array
    {
        if (! is_string($text) || trim($text) === '') {
            return [__(':name is missing', ['name' => $name])];
        }

        return array_map(
            fn (string $problem): string => __(':name — :problem', ['name' => $name, 'problem' => $problem]),
            QuestionImportText::problemsIn($text),
        );
    }

    /**
     * An "image" is the file name of an image given with the JSON —
     * nothing more (no folder, no web address), and found by that name
     * among the images (ImportedImages). Leaving it out is fine.
     *
     * @param  array<string, mixed>  $item
     * @return array<int, string>
     */
    private function imageProblems(array $item, string $name): array
    {
        $image = $item['image'] ?? null;

        if ($image === null) {
            return [];
        }

        $extensions = implode('|', ImportedImages::EXTENSIONS);

        return is_string($image) && mb_strlen($image) <= 150 && preg_match("/^[^\\/\\\\:]+\\.({$extensions})$/i", trim($image))
            ? []
            : [__('"image" of :name must be the file name of an image given with the JSON, ending in .png, .jpg or .webp — e.g. q12.png', ['name' => $name])];
    }

    /**
     * Marks go in halves from one half up, as on the question form.
     */
    private function isValidMarks(mixed $marks): bool
    {
        return (is_int($marks) || is_float($marks))
            && $marks >= 0.5
            && $marks <= 100
            && fmod($marks * 2, 1) === 0.0;
    }

    private function marksProblem(string $name): string
    {
        return __('"marks" of :name must be a number in halves (0.5, 1, 1.5 …)', ['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function typeOf(array $item): ?QuestionType
    {
        // A list of one kind lets "type" be left out — but a question
        // plainly of the other kind (sub-questions and no options, or the
        // other way round) is told it is in the wrong list, rather than
        // that it is a broken question of this one.
        $shapedAs = match (true) {
            isset($item['parts']) && ! isset($item['options']) => QuestionType::Cq,
            isset($item['options']) && ! isset($item['parts']) => QuestionType::Mcq,
            default => null,
        };

        $type = $item['type'] ?? ($this->onlyType ? ($shapedAs ?? $this->onlyType) : QuestionType::Mcq)->value;

        return is_string($type) ? QuestionType::tryFrom(strtolower(trim($type))) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function difficultyOf(array $item): ?Difficulty
    {
        $difficulty = $item['difficulty'] ?? null;

        return is_string($difficulty) ? Difficulty::tryFrom(strtolower(trim($difficulty))) : null;
    }

    /**
     * One question already found acceptable, in the shape read() returns.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function normalize(array $item): array
    {
        if ($this->isGroup($item)) {
            return [
                'stimulus' => QuestionImportText::toHtml($item['stimulus']),
                'stimulus_image' => $this->imageOf($item),
                'questions' => array_map(
                    fn (array $question): array => $this->normalize(['type' => QuestionType::Mcq->value, ...$question]),
                    $item['questions'],
                ),
            ];
        }

        $type = $this->typeOf($item);
        $html = fn (?string $text): ?string => filled($text) ? QuestionImportText::toHtml($text) : null;

        $question = [
            'type' => $type,
            'question' => QuestionImportText::toHtml($item['question']),
            'image' => $this->imageOf($item),
            'difficulty' => $this->withDifficulty ? $this->difficultyOf($item) : null,
        ];

        if ($type === QuestionType::Cq) {
            return [
                ...$question,
                'parts' => collect(CqPartType::ordered())
                    ->map(fn (CqPartType $partType, int $index): array => [
                        'part_type' => $partType,
                        'part_order' => $partType->order(),
                        'part_text' => QuestionImportText::toHtml($item['parts'][$index]['text']),
                        'part_image' => $this->imageOf($item['parts'][$index]),
                        'marks' => $item['parts'][$index]['marks'],
                        'answer' => $this->withCqAnswers ? $html($item['parts'][$index]['answer'] ?? null) : null,
                    ])
                    ->all(),
            ];
        }

        return [
            ...$question,
            'options' => collect($item['options'])
                ->map(fn (string|array $option, int $index): array => [
                    // An option that is only its figure has no text.
                    'option' => $html(is_array($option) ? (string) ($option['text'] ?? '') : $option) ?? '',
                    'image' => is_array($option) ? $this->imageOf($option) : null,
                    'is_correct' => $index + 1 === (int) $item['answer'],
                ])
                ->all(),
            'marks' => $item['marks'] ?? null,
            'explanation' => $html($item['explanation'] ?? null),
        ];
    }

    /**
     * The file name of an acceptable item's image, as given.
     *
     * @param  array<string, mixed>  $item
     */
    private function imageOf(array $item): ?string
    {
        return filled($item['image'] ?? null) ? trim($item['image']) : null;
    }

    /**
     * @param  array<int, string>  $problems
     *
     * @throws ValidationException
     */
    private function fail(array $problems): never
    {
        throw ValidationException::withMessages([self::ERROR_KEY => $problems]);
    }
}

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
 * left out), and a past paper's questions carry optional explanations and
 * CQ answers (`$withAnswers`) but no difficulty (`$withDifficulty`).
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
        private readonly bool $withAnswers = false,
        private readonly bool $withDifficulty = true,
    ) {}

    /**
     * Every question of the JSON, read and normalized — the text already
     * as stored (HTML, formulas as KaTeX embeds):
     * `type`, `question`, `difficulty` (null when not given), and for an
     * MCQ `options` (each `option`/`image`/`is_correct`), `marks` (null when
     * not given) and `explanation`, for a CQ `parts` (each `part_type`,
     * `part_order`, `part_text`, `marks` and `answer`). MCQs sharing a
     * stimulus come as one entry: `stimulus` (its text) and `questions`
     * (those MCQs, read the same way).
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
            $this->withAnswers && $this->onlyType !== QuestionType::Cq
                ? __('"explanation" (may be left out): why the answer is the right one.')
                : null,
            $this->onlyType !== QuestionType::Cq
                ? __('MCQs that share a stimulus go in one object: "stimulus" is its text, and "questions" lists :min to :max MCQs. Do not write "answer questions 42 and 43" — the paper numbers them itself.', ['min' => QuestionStimulus::MIN_QUESTIONS, 'max' => QuestionStimulus::MAX_QUESTIONS])
                : null,
            $this->withAnswers && $this->onlyType !== QuestionType::Mcq
                ? __('"answer" on a sub-question (may be left out): its answer.')
                : null,
            __('Maths: write every formula as LaTeX between $ signs, write every backslash twice (:twice, not \frac), and keep Bangla text outside the $ signs.', ['twice' => '\\\\frac']),
            __('Plain text only — no HTML, no Markdown, no images.'),
        ]));

        return collect($rules)->map(fn (string $rule): string => "- {$rule}")->implode("\n")."\n\n".$this->sampleJson();
    }

    /**
     * The example of the format: an MCQ and a CQ, or just the one kind
     * this list takes.
     */
    public function sampleJson(): string
    {
        $mcq = array_filter([
            'type' => $this->onlyType ? null : QuestionType::Mcq->value,
            'question' => '$x^2 = 9$ হলে $x$ এর ধনাত্মক মান কত?',
            'options' => ['$2$', '$3$', '$\frac{9}{2}$', '$9$'],
            'answer' => 2,
            'difficulty' => $this->withDifficulty ? Difficulty::Easy->value : null,
            'explanation' => $this->withAnswers ? '$3^2 = 9$, তাই $x = 3$।' : null,
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
            fn (array $part): array => array_filter($part, fn (mixed $value, string $key): bool => $key !== 'answer' || ($this->withAnswers && $value !== null), ARRAY_FILTER_USE_BOTH),
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
                    'explanation' => $this->withAnswers ? '$I = \\frac{20}{2} = 10$ A।' : null,
                ], fn (mixed $value): bool => $value !== null),
            ],
        ];

        $sample = match ($this->onlyType) {
            QuestionType::Mcq => [$mcq, $group],
            QuestionType::Cq => [$cq],
            default => [$mcq, $cq, $group],
        };

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

        $problems = $this->textProblems($item['question'] ?? null, __('the question text'));

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

        $problems = $this->textProblems($item['stimulus'], __('the stimulus'));
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
            array_push($problems, ...$this->textProblems($option, __('option :number', ['number' => $index + 1])));
        }

        $answer = $item['answer'] ?? null;

        if (filter_var($answer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => count($options)]]) === false) {
            $problems[] = __('"answer" must be the number of the correct option, from 1 to :max', ['max' => count($options)]);
        }

        if (isset($item['marks']) && ! $this->isValidMarks($item['marks'])) {
            $problems[] = $this->marksProblem(__('the question'));
        }

        if ($this->withAnswers && isset($item['explanation'])) {
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

            // No default here: how many marks each part carries is the
            // author's call, not something the code assumes.
            if (! $this->isValidMarks($part['marks'] ?? null)) {
                $problems[] = $this->marksProblem($name);
            }

            if ($this->withAnswers && isset($part['answer'])) {
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
                        'marks' => $item['parts'][$index]['marks'],
                        'answer' => $this->withAnswers ? $html($item['parts'][$index]['answer'] ?? null) : null,
                    ])
                    ->all(),
            ];
        }

        return [
            ...$question,
            'options' => collect($item['options'])
                ->map(fn (string $option, int $index): array => [
                    'option' => QuestionImportText::toHtml($option),
                    'image' => null,
                    'is_correct' => $index + 1 === (int) $item['answer'],
                ])
                ->all(),
            'marks' => $item['marks'] ?? null,
            'explanation' => $this->withAnswers ? $html($item['explanation'] ?? null) : null,
        ];
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

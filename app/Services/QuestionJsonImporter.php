<?php

namespace App\Services;

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionType;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * Adds many questions to one chapter at once from pasted JSON — the quick
 * way in for questions drafted with an AI tool. The one place that reads
 * that JSON, decides whether it is acceptable, and creates the questions.
 *
 * Nothing about a question's fate is decided here: each one is created the
 * ordinary way, so it starts pending or approved exactly as a question
 * typed into the form would (QuestionObserver), and a CQ's marks are still
 * the sum of its parts.
 *
 * It is all or nothing — one unacceptable question and none is saved, so
 * the user fixes the JSON and pastes it again rather than working out
 * which half went in.
 */
class QuestionJsonImporter
{
    /**
     * The field of the import form the problems are reported against.
     */
    public const ERROR_KEY = 'json';

    /**
     * The JSON as the questions it describes — built but not saved, which is
     * also what the preview shows.
     *
     * @return Collection<int, Question>
     *
     * @throws ValidationException listing every problem found
     */
    public function parse(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): Collection
    {
        $items = $this->decode($json);
        $max = BillingSetting::current()->question_import_max;

        if (count($items) > $max) {
            $this->fail([__('There are :count questions here, but at most :max can be added at once.', ['count' => count($items), 'max' => $max])]);
        }

        if (filled($topicId) && ! Topic::where('chapter_id', $chapter->id)->whereKey($topicId)->exists()) {
            $this->fail([__('The chosen topic does not belong to this chapter.')]);
        }

        $problems = [];
        $questions = collect();

        foreach ($items as $index => $item) {
            $itemProblems = $this->problemsWith($item);

            foreach ($itemProblems as $problem) {
                $problems[] = __('Question :number: :problem', ['number' => $index + 1, 'problem' => $problem]);
            }

            if ($itemProblems === []) {
                $questions->push($this->build($item, $chapter, $topicId, $defaultDifficulty));
            }
        }

        if ($problems !== []) {
            $this->fail($problems);
        }

        return $questions;
    }

    /**
     * Saves the questions of the JSON as the signed-in user's own. A
     * question that user can already see in this chapter — or that is in
     * the JSON twice — is left out rather than added again.
     *
     * @return array{added: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function import(string $json, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): array
    {
        Gate::authorize('import', Question::class);

        $questions = $this->parse($json, $chapter, $topicId, $defaultDifficulty);
        $author = Auth::user();

        $alreadyThere = Question::query()
            ->where('chapter_id', $chapter->id)
            ->where(fn (Builder $query) => $query->visibleTo($author))
            ->whereIn('question_text', $questions->pluck('question_text'))
            ->pluck('question_text')
            ->flip();

        $added = DB::transaction(function () use ($questions, $alreadyThere): int {
            $added = 0;

            foreach ($questions as $question) {
                if ($alreadyThere->has($question->question_text)) {
                    continue;
                }

                $alreadyThere->put($question->question_text, true);

                $parts = $question->cqParts;
                $question->unsetRelation('cqParts');
                $question->save();
                $question->cqParts()->saveMany($parts);

                $added++;
            }

            return $added;
        });

        Log::info('Questions imported from JSON.', [
            'imported_by' => $author->id,
            'chapter_id' => $chapter->id,
            'added' => $added,
            'skipped' => $questions->count() - $added,
        ]);

        return ['added' => $added, 'skipped' => $questions->count() - $added];
    }

    /**
     * What a user copies and hands to an AI tool to have questions written
     * in the format: the rules in words, then the example. The rules are
     * stated from the same limits the import checks against.
     */
    public static function formatGuide(): string
    {
        $rules = [
            __('Write the questions as a JSON list in exactly the format below, and reply with the JSON only.'),
            __('"type" is "mcq" or "cq". "difficulty" is one of: :values.', ['values' => implode(', ', array_column(Difficulty::cases(), 'value'))]),
            __('MCQ: "options" has :min to :max options, and "answer" is the number of the correct one (1 = the first option). "marks" may be left out.', ['min' => Question::MIN_MCQ_OPTIONS, 'max' => Question::MAX_MCQ_OPTIONS]),
            __('CQ: "question" is the stimulus, and "parts" has exactly :count sub-questions in this order — :order — each with its "text" and "marks".', [
                'count' => count(CqPartType::ordered()),
                'order' => implode(', ', array_map(fn (CqPartType $type): string => $type->getLabel(), CqPartType::ordered())),
            ]),
            __('Maths: write every formula as LaTeX between $ signs, write every backslash twice (:twice, not \frac), and keep Bangla text outside the $ signs.', ['twice' => '\\\\frac']),
            __('Plain text only — no HTML, no Markdown, no images.'),
        ];

        return collect($rules)->map(fn (string $rule): string => "- {$rule}")->implode("\n")."\n\n".self::sampleJson();
    }

    /**
     * The example of the format, one MCQ and one CQ.
     */
    public static function sampleJson(): string
    {
        return json_encode([
            [
                'type' => QuestionType::Mcq->value,
                'question' => '$x^2 = 9$ হলে $x$ এর ধনাত্মক মান কত?',
                'options' => ['$2$', '$3$', '$\frac{9}{2}$', '$9$'],
                'answer' => 2,
                'difficulty' => Difficulty::Easy->value,
            ],
            [
                'type' => QuestionType::Cq->value,
                'question' => 'একটি আয়তক্ষেত্রের দৈর্ঘ্য $x$ মিটার এবং প্রস্থ $y$ মিটার।',
                'parts' => [
                    ['text' => 'আয়তক্ষেত্র কাকে বলে?', 'marks' => 1],
                    ['text' => 'ক্ষেত্রফলের সূত্রটি ব্যাখ্যা করো।', 'marks' => 2],
                    ['text' => '$x = 5$ ও $y = 3$ হলে পরিসীমা নির্ণয় করো।', 'marks' => 3],
                    ['text' => 'দৈর্ঘ্য দ্বিগুণ করলে ক্ষেত্রফলের পরিবর্তন বিশ্লেষণ করো।', 'marks' => 4],
                ],
                'difficulty' => Difficulty::Medium->value,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
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

        $type = $this->typeOf($item);

        if (! $type) {
            return [__('"type" must be "mcq" or "cq"')];
        }

        $problems = $this->textProblems($item['question'] ?? null, __('the question text'));

        if (isset($item['difficulty']) && ! $this->difficultyOf($item)) {
            $problems[] = __('"difficulty" must be one of: :values', ['values' => implode(', ', array_column(Difficulty::cases(), 'value'))]);
        }

        return [
            ...$problems,
            ...($type === QuestionType::Mcq ? $this->mcqProblems($item) : $this->cqProblems($item)),
        ];
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
        $type = $item['type'] ?? QuestionType::Mcq->value;

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
     * @param  array<string, mixed>  $item  One question already found acceptable.
     */
    private function build(array $item, Chapter $chapter, int|string|null $topicId, Difficulty $defaultDifficulty): Question
    {
        $type = $this->typeOf($item);

        $question = new Question([
            'chapter_id' => $chapter->id,
            'topic_id' => filled($topicId) ? (int) $topicId : null,
            'question_type' => $type,
            'question_text' => QuestionImportText::toHtml($item['question']),
            // The formulas are CKEditor's kind of content, so that is the
            // editor the question opens in when it is edited later.
            'editor_mode' => EditorMode::CkEditor,
            'difficulty' => $this->difficultyOf($item) ?? $defaultDifficulty,
        ]);

        if ($type === QuestionType::Cq) {
            return $question->setRelation('cqParts', $this->buildCqParts($item['parts']));
        }

        return $question
            ->fill([
                'options' => collect($item['options'])
                    ->map(fn (string $option, int $index): array => [
                        'option' => QuestionImportText::toHtml($option),
                        'image' => null,
                        'is_correct' => $index + 1 === (int) $item['answer'],
                    ])
                    ->all(),
                'marks' => $item['marks'] ?? Question::DEFAULT_MCQ_MARKS,
            ])
            ->setRelation('cqParts', collect());
    }

    /**
     * @param  array<int, array{text: string, marks: int|float}>  $parts
     * @return Collection<int, QuestionCqPart>
     */
    private function buildCqParts(array $parts): Collection
    {
        return collect(CqPartType::ordered())
            ->map(fn (CqPartType $type, int $index): QuestionCqPart => new QuestionCqPart([
                'part_type' => $type,
                'part_order' => $type->order(),
                'part_text' => QuestionImportText::toHtml($parts[$index]['text']),
                'marks' => $parts[$index]['marks'],
            ]));
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

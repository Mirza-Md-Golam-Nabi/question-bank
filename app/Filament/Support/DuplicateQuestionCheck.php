<?php

namespace App\Filament\Support;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\BoardCqQuestion;
use App\Models\BoardCqQuestionPart;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Question;
use App\Models\QuestionCqPart;
use App\Models\QuestionStimulus;
use App\Services\DuplicateMatch;
use App\Services\DuplicateQuestionFinder;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The duplicate check of a question form, on saving: the same question is
 * refused; one that only looks like a question already there
 * (DuplicateQuestionFinder) is saved once the user ticks "It is a
 * different question". The box shows itself only when it has something
 * to say.
 *
 * What a form's state is checked against is said here for each kind of
 * form: a chapter's question, a chapter's stimulus group, a past paper's
 * question and a past paper's stimulus group.
 */
class DuplicateQuestionCheck
{
    public const FIELD = 'save_despite_duplicate';

    /**
     * @param  Closure(Get, ?Model): ?DuplicateMatch  $find  What the form's question (or one of its questions) duplicates.
     */
    public static function field(Closure $find): Checkbox
    {
        return Checkbox::make(self::FIELD)
            ->label(__('It is a different question — save it anyway'))
            ->default(false)
            ->dehydrated(false)
            ->columnSpanFull()
            ->extraFieldWrapperAttributes(fn (Checkbox $component): array => $component->getState()
                || $component->getLivewire()->getErrorBag()->has($component->getStatePath()) ? [] : ['class' => 'hidden'])
            ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($find, $get, $record): void {
                $match = $find($get, $record);

                if ($match === null || ($value && ! $match->isExact)) {
                    return;
                }

                $details = [
                    'percent' => $match->percent,
                    'question' => Str::limit(trim(html_entity_decode(strip_tags((string) $match->question->question_text))), 120),
                ];

                $message = $match->isExact
                    ? __('The same question is already there: “:question”. It will not be saved again.', $details)
                    : __('This looks like a question already there (:percent% alike): “:question”. If it is a different question, tick the box and save again.', $details);

                $fail($match->subject ? "{$match->subject}: {$message}" : $message);
            });
    }

    /**
     * A question of a chapter's form, against the chapter's other questions
     * the user can see — its own versions aside.
     */
    public static function inChapter(Get $get, ?Model $record): ?DuplicateMatch
    {
        $chapterId = (int) $get('chapter_id');

        if (! $chapterId || blank($get('question_text'))) {
            return null;
        }

        $question = static::question(new Question(['question_type' => $get('question_type')]), $get);

        return DuplicateQuestionFinder::make()->bestMatch(
            $question,
            static::chapterQuestions($chapterId)->reject(fn (Question $other): bool => $record instanceof Question && $other->isVersionOf($record)),
        );
    }

    /**
     * What a question about to be approved — or each question of its
     * stimulus group, which is approved with it — looks like among the
     * chapter's other current questions not rejected: for the Admin to see
     * before approving.
     *
     * @param  Collection<int, Question>|null  $chapterQuestions  The chapter's questions when already read (chapterQuestions()) — approving many at once reads them once.
     * @return list<DuplicateMatch>
     */
    public static function forApproval(Question $question, ?Collection $chapterQuestions = null): array
    {
        $group = $question->stimulus_id !== null
            ? Question::query()->where('stimulus_id', $question->stimulus_id)->where('is_latest', true)->with(['cqParts', 'stimulus'])->orderBy('stimulus_order')->get()
            : collect([$question]);

        $candidates = ($chapterQuestions ?? static::chapterQuestions($question->chapter_id))
            ->reject(fn (Question $other): bool => $other->status === QuestionStatus::Rejected
                || $group->contains(fn (Question $own): bool => $other->isVersionOf($own)));

        $finder = DuplicateQuestionFinder::make();

        return $group->values()
            ->map(function (Question $own, int $index) use ($finder, $candidates, $group): ?DuplicateMatch {
                $match = $finder->bestMatch($own, $candidates);

                return $match && $group->count() > 1 ? $match->about(__('Question :number', ['number' => $index + 1])) : $match;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The questions of a chapter's stimulus group, against the chapter's
     * other questions the user can see — the group's own aside.
     */
    public static function inChapterGroup(Get $get, ?Model $record): ?DuplicateMatch
    {
        $chapterId = (int) $get('chapter_id');

        if (! $chapterId) {
            return null;
        }

        return static::groupMatch(
            $get,
            new QuestionStimulus,
            fn (): Question => new Question(['question_type' => QuestionType::Mcq]),
            static::chapterQuestions($chapterId)->reject(fn (Question $other): bool => $record?->getAttribute('stimulus_id') !== null && $other->stimulus_id === $record->stimulus_id),
        );
    }

    /**
     * A question of a past paper's list, against that list's others.
     */
    public static function onPaper(BoardQuestionPaper $paper, QuestionType $type, Get $get, ?Model $record): ?DuplicateMatch
    {
        if (blank($get('question_text'))) {
            return null;
        }

        $question = static::question($type === QuestionType::Mcq ? new BoardMcqQuestion : new BoardCqQuestion, $get);

        return DuplicateQuestionFinder::make()->bestMatch(
            $question,
            $paper->duplicateCandidates($type)->reject(fn (Model $other): bool => $record !== null && $other->is($record)),
        );
    }

    /**
     * The MCQs of a past paper's stimulus group, against the paper's other
     * MCQs — the group's own aside.
     */
    public static function onPaperGroup(BoardQuestionPaper $paper, Get $get, ?Model $record): ?DuplicateMatch
    {
        return static::groupMatch(
            $get,
            new BoardMcqStimulus,
            fn (): BoardMcqQuestion => new BoardMcqQuestion,
            $paper->duplicateCandidates(QuestionType::Mcq)->reject(fn (Model $other): bool => $record?->getAttribute('stimulus_id') !== null && $other->getAttribute('stimulus_id') === $record->getAttribute('stimulus_id')),
        );
    }

    /**
     * The first of a group form's questions that duplicates one of the
     * candidates, said for which question it is.
     *
     * @param  Closure(): Model  $newQuestion
     * @param  Collection<int, Model>  $candidates
     */
    protected static function groupMatch(Get $get, Model $stimulus, Closure $newQuestion, Collection $candidates): ?DuplicateMatch
    {
        $stimulus->text = (string) QuestionFormSchema::resolvePreviewHtml($get('question_text'));
        $finder = DuplicateQuestionFinder::make();

        foreach (array_values($get('questions') ?? []) as $index => $item) {
            if (blank($item['question_text'] ?? null)) {
                continue;
            }

            $question = $newQuestion()->forceFill([
                'question_text' => QuestionFormSchema::resolvePreviewHtml($item['question_text']),
                'options' => QuestionFormData::storedOptions($item['options'] ?? []),
            ])->setRelation('stimulus', $stimulus)->setRelation('cqParts', collect());

            if ($match = $finder->bestMatch($question, $candidates)) {
                return $match->about(__('Question :number', ['number' => $index + 1]));
            }
        }

        return null;
    }

    /**
     * An unsaved question with what the form says it is: its text, and its
     * options (an MCQ) or sub-questions (a CQ).
     */
    protected static function question(Model $question, Get $get): Model
    {
        $question->forceFill(['question_text' => QuestionFormSchema::resolvePreviewHtml($get('question_text'))]);

        $isMcq = $question->question_type === QuestionType::Mcq;
        $partModel = $question instanceof BoardCqQuestion ? BoardCqQuestionPart::class : QuestionCqPart::class;

        if ($isMcq) {
            $question->forceFill(['options' => QuestionFormData::storedOptions($get('options') ?? [])]);
        }

        return $question->setRelation('cqParts', $isMcq ? collect() : collect(QuestionFormData::cqPartRows($get('cq_parts')) ?? [])
            ->map(fn (array $part): Model => (new $partModel)->forceFill(['part_text' => QuestionFormSchema::resolvePreviewHtml($part['part_text'])])));
    }

    /**
     * The chapter's questions a question is compared against — public so
     * that approving several questions of a chapter reads them once
     * (forApproval()'s second argument).
     *
     * @return Collection<int, Question>
     */
    public static function chapterQuestions(int $chapterId): Collection
    {
        return Question::query()->duplicateCandidatesIn($chapterId, Auth::user())->get();
    }
}

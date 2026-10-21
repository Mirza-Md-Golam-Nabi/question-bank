<?php

namespace App\Filament\Support;

use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Admin's review of the question bank's questions, on every panel's
 * question table: approve / reject one row, many selected rows at once, or
 * from the "View" window — which then moves straight on to the next
 * pending question of the list. Approving and rejecting themselves are
 * Question::approve() / reject() (a stimulus group together, a Staff
 * member paid); only an Admin ever sees these (QuestionPolicy).
 */
class QuestionReviewActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->label(__('Approve'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (Question $record) => self::canApprove($record))
            ->requiresConfirmation()
            // Whether it looks like a question already in the chapter — seen
            // before the question goes into the pool (and a Staff member is paid).
            ->modalContent(fn (Question $record): View => self::duplicateWarnings($record))
            ->action(fn (Question $record) => $record->approve(Auth::user()));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label(__('Reject'))
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (Question $record) => self::canReject($record))
            ->schema([self::reasonField()->required()])
            ->action(fn (Question $record, array $data) => $record->reject(Auth::user(), $data['rejection_reason']));
    }

    /**
     * The "View" window — and, for an Admin, Approve / Reject / Next below
     * the question. After each the window shows the next pending question
     * of the list as it is filtered and sorted, so there is nothing to
     * click open again.
     */
    public static function view(): Action
    {
        return Action::make('view')
            ->label(__('View'))
            ->icon(Heroicon::OutlinedEye)
            ->iconButton()
            ->modalHeading(__('View question'))
            ->schema(fn (Question $record): array => [
                ...QuestionInfolist::components(),
                ...self::canReview($record) ? [
                    ViewComponent::make('filament.support.questions.duplicate-warnings')
                        ->viewData(fn (): array => ['matches' => self::canApprove($record) ? DuplicateQuestionCheck::forApproval($record) : []]),
                    self::reasonField()
                        ->helperText(__('Needed only to reject.'))
                        ->visible(self::canReject($record)),
                ] : [],
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->extraModalFooterActions(fn (Action $action, Question $record): array => self::canReview($record) ? array_values(array_filter([
                self::canApprove($record)
                    ? $action->makeModalSubmitAction('approveAndNext', ['decision' => 'approve'])->label(__('Approve'))->color('success')->icon('heroicon-o-check-circle')
                    : null,
                self::canReject($record)
                    ? $action->makeModalSubmitAction('rejectAndNext', ['decision' => 'reject'])->label(__('Reject'))->color('danger')->icon('heroicon-o-x-circle')
                    : null,
                $action->makeModalSubmitAction('next', ['decision' => 'next'])->label(__('Next question'))->color('gray')->icon('heroicon-o-arrow-right'),
            ])) : [])
            ->action(function (Question $record, array $data, array $arguments, HasTable $livewire, Action $action): void {
                // The list's order before anything changes — a status filter
                // would otherwise drop this question from it.
                $listed = $livewire->getFilteredSortedTableQuery()->pluck('questions.id')->all();

                match ($arguments['decision'] ?? null) {
                    'approve' => self::canApprove($record) ? $record->approve(Auth::user()) : null,
                    'reject' => self::rejectFromWindow($record, $data, $action),
                    default => null,
                };

                $nextId = self::nextPendingAfter($record->id, $listed);

                if ($nextId === null) {
                    Notification::make()->title(__('No more pending questions in this list.'))->success()->send();

                    return;
                }

                $livewire->replaceMountedAction('view', context: ['table' => true, 'recordKey' => $nextId]);
            });
    }

    /**
     * Approves the selected questions at once — leaving out those that look
     * like a question already in their chapter, for the Admin to look at
     * one by one.
     */
    public static function approveSelected(): BulkAction
    {
        return BulkAction::make('approveSelected')
            ->label(__('Approve selected'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (): bool => (bool) Auth::user()?->role->isAdmin())
            ->requiresConfirmation()
            ->modalDescription(__('Questions that look like one already in their chapter are left out, to be approved one by one.'))
            ->action(function (Collection $records): void {
                ['approved' => $approved, 'duplicates' => $duplicates] = self::approveMany($records, Auth::user());

                Notification::make()
                    ->title(__(':count approved', ['count' => $approved]))
                    ->body($duplicates > 0 ? __(':count left out as possible duplicates — approve them one by one.', ['count' => $duplicates]) : null)
                    ->color($duplicates > 0 ? 'warning' : 'success')
                    ->icon($duplicates > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Rejects the selected questions at once, with one reason for all.
     */
    public static function rejectSelected(): BulkAction
    {
        return BulkAction::make('rejectSelected')
            ->label(__('Reject selected'))
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (): bool => (bool) Auth::user()?->role->isAdmin())
            ->schema([self::reasonField()->required()])
            ->action(function (Collection $records, array $data): void {
                $rejected = 0;

                foreach ($records as $question) {
                    // A group's earlier question may have rejected this one already.
                    if (self::canReject($question->refresh())) {
                        $question->reject(Auth::user(), $data['rejection_reason']);
                        $rejected++;
                    }
                }

                Notification::make()->title(__(':count rejected', ['count' => $rejected]))->success()->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Approves each question that can be, skipping the ones that look like
     * a question already in their chapter. The chapter's questions are
     * read once per chapter, not once per question.
     *
     * @param  Collection<int, Question>  $questions
     * @return array{approved: int, duplicates: int}
     */
    public static function approveMany(Collection $questions, User $admin): array
    {
        $approved = 0;
        $duplicates = 0;
        $chapterQuestions = [];

        foreach ($questions as $question) {
            // A group's earlier question may have approved this one already.
            if (! self::canApprove($question->refresh())) {
                continue;
            }

            $chapterQuestions[$question->chapter_id] ??= DuplicateQuestionCheck::chapterQuestions($question->chapter_id);

            if (DuplicateQuestionCheck::forApproval($question, $chapterQuestions[$question->chapter_id]) !== []) {
                $duplicates++;

                continue;
            }

            $question->approve($admin);
            $approved++;
        }

        return ['approved' => $approved, 'duplicates' => $duplicates];
    }

    /**
     * The first pending question after `$currentId` in the list, going on
     * from its start when the end is reached; null when none is left.
     *
     * @param  array<int, int>  $listedIds  The list's question ids, in its order.
     */
    public static function nextPendingAfter(int $currentId, array $listedIds): ?int
    {
        $pending = Question::query()
            ->whereKey($listedIds)
            ->whereKeyNot($currentId)
            ->where('status', QuestionStatus::Pending)
            ->pluck('id')
            ->flip();

        $position = array_search($currentId, $listedIds, true);
        $inOrder = $position === false
            ? $listedIds
            : [...array_slice($listedIds, $position + 1), ...array_slice($listedIds, 0, $position)];

        foreach ($inOrder as $id) {
            if ($pending->has($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    private static function rejectFromWindow(Question $record, array $data, Action $action): void
    {
        if (! self::canReject($record)) {
            return;
        }

        $reason = trim((string) ($data['rejection_reason'] ?? ''));

        if ($reason === '') {
            Notification::make()->title(__('Write a reason to reject the question.'))->danger()->send();

            $action->halt();
        }

        $record->reject(Auth::user(), $reason);
    }

    private static function reasonField(): Textarea
    {
        return Textarea::make('rejection_reason')->label(__('Reason'));
    }

    private static function duplicateWarnings(Question $record): View
    {
        return view('filament.support.questions.duplicate-warnings', ['matches' => DuplicateQuestionCheck::forApproval($record)]);
    }

    private static function canReview(Question $record): bool
    {
        return self::canApprove($record) || self::canReject($record);
    }

    private static function canApprove(Question $record): bool
    {
        return $record->status !== QuestionStatus::Approved && (bool) Auth::user()?->can('approve', $record);
    }

    private static function canReject(Question $record): bool
    {
        return $record->status !== QuestionStatus::Rejected && (bool) Auth::user()?->can('reject', $record);
    }
}

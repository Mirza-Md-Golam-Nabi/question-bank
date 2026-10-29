<?php

namespace App\Filament\Pages;

use App\Enums\DuplicateCheckStatus;
use App\Enums\QuestionStatus;
use App\Filament\Support\Concerns\SwitchesCardAndTableView;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\Question;
use App\Models\QuestionDuplicateMatch;
use App\Services\QuestionDuplicateScreening;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View as CardColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The questions the background duplicate check holds for the Admin
 * (QuestionDuplicateScreening): each beside the questions already in its
 * chapter it looks like, to keep — it then goes on as any other question —
 * or to throw away for good. Also where the questions already there are
 * looked through for duplicates.
 */
class DuplicateQuestions extends Page implements HasTable
{
    use InteractsWithTable;
    use SwitchesCardAndTableView;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.duplicate-questions';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $title = 'Possible duplicates';

    /**
     * Always there, 0 included — so whether anything waits is seen at a
     * glance: red while something does, grey at 0.
     */
    public static function getNavigationBadge(): ?string
    {
        return (string) self::waitingCount();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::waitingCount() > 0 ? 'danger' : 'gray';
    }

    /**
     * How many wait for the Admin — counted once for the badge and its colour.
     */
    private static function waitingCount(): int
    {
        return once(fn (): int => self::heldQuestions()->where('duplicate_status', DuplicateCheckStatus::Suspected)->count());
    }

    protected function getHeaderActions(): array
    {
        return [$this->switchDisplayAction()];
    }

    protected function displayUrl(string $display): string
    {
        return static::getUrl(['view' => $display]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::heldQuestions()->with([
                'chapter.classSubject.academicClass',
                'chapter.classSubject.subject',
                'creator',
                // A card shows the question whole, beside the ones it looks like.
                ...$this->showsCards() ? [
                    'topic:id,name',
                    'cqParts',
                    'stimulus',
                    'duplicateMatches.matchedQuestion.cqParts',
                    'duplicateMatches.matchedQuestion.stimulus',
                ] : ['duplicateMatches'],
            ]))
            ->defaultSort('id', 'desc')
            ->columns($this->showsCards() ? [CardColumn::make('filament.pages.duplicate-questions.card')] : [
                TextColumn::make('question_text')->label(__('Question'))
                    ->formatStateUsing(fn (?string $state): string => Str::limit(trim(html_entity_decode(strip_tags((string) $state))), 90))
                    ->wrap(),
                TextColumn::make('chapter.name')->label(__('Chapter'))
                    ->description(fn (Question $record): ?string => $record->chapter?->classSubject?->label()),
                TextColumn::make('question_type')->badge(),
                TextColumn::make('alike')->label(__('Alike'))
                    ->state(fn (Question $record): ?int => $record->duplicateMatches->first()?->percent)
                    ->suffix('%')
                    ->badge()
                    ->color('danger')
                    ->placeholder('—'),
                TextColumn::make('duplicate_status')->label(__('Duplicate check'))->badge()
                    ->color(fn (DuplicateCheckStatus $state): string => $state->getColor()),
                TextColumn::make('status')->badge()->color(fn (QuestionStatus $state): string => $state->getColor()),
                TextColumn::make('creator.name')->label(__('Created by')),
                TextColumn::make('created_at')->localDateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('duplicate_status')
                    ->label(__('Duplicate check'))
                    ->options([
                        DuplicateCheckStatus::Suspected->value => DuplicateCheckStatus::Suspected->getLabel(),
                        DuplicateCheckStatus::Checking->value => DuplicateCheckStatus::Checking->getLabel(),
                    ])
                    ->default(DuplicateCheckStatus::Suspected->value),
            ])
            ->headerActions([$this->lookThroughExistingAction()])
            ->recordActions([
                $this->compareAction(),
                $this->keepAction(),
                $this->discardAction(),
                $this->checkAgainAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('keepSelected')
                        ->label(__('Keep selected — they are different questions'))
                        ->color('success')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $kept = $records->filter(fn (Question $question): bool => self::isHeld($question))
                                ->each(fn (Question $question) => QuestionDuplicateScreening::keep($question, Auth::user()))
                                ->count();

                            Notification::make()->title(__(':count kept', ['count' => $kept]))->success()->send();
                            $this->refreshMenuCount();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('discardSelected')
                        ->label(__('Delete selected for good'))
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalDescription(__('They are deleted for good. One already on an exam is rejected instead, so that exam stays as it is.'))
                        ->action(function (Collection $records): void {
                            $deleted = 0;
                            $rejected = 0;

                            foreach ($records as $question) {
                                // A group's earlier question may have rejected this one already.
                                if (self::isHeld($question->refresh())) {
                                    QuestionDuplicateScreening::discard($question, Auth::user()) ? $deleted++ : $rejected++;
                                }
                            }

                            $this->discarded($deleted, $rejected);
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * The questions held by the duplicate check — a School's own never are.
     *
     * @return Builder<Question>
     */
    private static function heldQuestions(): Builder
    {
        return Question::query()
            ->where('is_private', false)
            ->whereIn('duplicate_status', [DuplicateCheckStatus::Suspected, DuplicateCheckStatus::Checking]);
    }

    private static function isHeld(Question $question): bool
    {
        return $question->exists && $question->duplicate_status === DuplicateCheckStatus::Suspected;
    }

    /**
     * The question beside the ones it looks like — and Keep / Delete below.
     */
    private function compareAction(): Action
    {
        return Action::make('compare')
            ->label(__('Compare'))
            ->icon(Heroicon::OutlinedEye)
            ->iconButton()
            ->modalHeading(__('Compare with the questions already there'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalContent(fn (Question $record): View => view('filament.pages.duplicate-questions.compare', [
                'question' => $record->load(['cqParts', 'stimulus']),
                'matches' => $record->duplicateMatches()
                    ->with(['matchedQuestion.cqParts', 'matchedQuestion.stimulus'])
                    ->get()
                    ->filter(fn (QuestionDuplicateMatch $match): bool => $match->matchedQuestion !== null)
                    ->map(fn (QuestionDuplicateMatch $match) => $match->toDuplicateMatch())
                    ->values()
                    ->all(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->extraModalFooterActions(fn (Action $action, Question $record): array => self::isHeld($record) ? [
                $action->makeModalSubmitAction('keep', ['decision' => 'keep'])
                    ->label(__('Keep — it is a different question'))->color('success')->icon('heroicon-o-check-circle'),
                $action->makeModalSubmitAction('discard', ['decision' => 'discard'])
                    ->label(__('Delete for good'))->color('danger')->icon('heroicon-o-trash'),
            ] : [])
            ->action(function (Question $record, array $arguments): void {
                if (! self::isHeld($record)) {
                    return;
                }

                match ($arguments['decision'] ?? null) {
                    'keep' => $this->keep($record),
                    'discard' => $this->discard($record),
                    default => null,
                };
            });
    }

    private function keepAction(): Action
    {
        return Action::make('keep')
            ->label(__('Keep'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (Question $record): bool => self::isHeld($record))
            ->requiresConfirmation()
            ->modalDescription(__('It is a different question: it goes on to be approved and used as any other.'))
            ->action(fn (Question $record) => $this->keep($record));
    }

    private function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('Delete'))
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->visible(fn (Question $record): bool => self::isHeld($record))
            ->requiresConfirmation()
            ->modalDescription(__('It is deleted for good. If it is already on an exam, it is rejected instead, so that exam stays as it is.'))
            ->action(fn (Question $record) => $this->discard($record));
    }

    /**
     * For a question whose check has not run (the queue was down).
     */
    private function checkAgainAction(): Action
    {
        return Action::make('checkAgain')
            ->label(__('Check again'))
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Question $record): bool => $record->duplicate_status === DuplicateCheckStatus::Checking)
            ->action(function (Question $record): void {
                QuestionDuplicateScreening::queue($record);

                Notification::make()->title(__('It will be checked again in a minute or so.'))->success()->send();
            });
    }

    private function lookThroughExistingAction(): Action
    {
        return Action::make('lookThroughExisting')
            ->label(__('Look for duplicates among the questions already there'))
            ->icon('heroicon-o-magnifying-glass')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Every chapter is looked through in the background, newest question first: of two alike, the later one is held here. It can take a while; the questions it finds show up here as they are found.'))
            ->action(function (): void {
                $chapters = QuestionDuplicateScreening::queueEveryChapter(Auth::user());

                Notification::make()->title(__(':count chapters will be looked through in the background.', ['count' => $chapters]))->success()->send();
            });
    }

    private function keep(Question $question): void
    {
        QuestionDuplicateScreening::keep($question, Auth::user());

        Notification::make()->title(__('Kept — it goes on as any other question.'))->success()->send();
        $this->refreshMenuCount();
    }

    private function discard(Question $question): void
    {
        $deleted = QuestionDuplicateScreening::discard($question, Auth::user());

        $this->discarded((int) $deleted, (int) ! $deleted);
    }

    private function discarded(int $deleted, int $rejected): void
    {
        Notification::make()
            ->title(__(':count deleted for good', ['count' => $deleted]))
            ->body($rejected > 0 ? __(':count already on an exam — rejected instead.', ['count' => $rejected]) : null)
            ->success()
            ->send();

        $this->refreshMenuCount();
    }

    /**
     * The menu's count of waiting questions, redrawn at once — not only on
     * the next page load.
     */
    private function refreshMenuCount(): void
    {
        $this->dispatch('refresh-sidebar');
    }
}

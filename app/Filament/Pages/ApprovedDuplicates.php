<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\ContentHierarchySchema;
use App\Filament\Support\NavigationGroup;
use App\Models\AcademicClass;
use App\Models\ApprovedDuplicateSet;
use App\Models\ApprovedDuplicateSetMember;
use App\Services\ApprovedDuplicateSweep;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View as CardColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The sets of alike approved questions the duplicate check of the
 * approved questions found (ApprovedDuplicateSweep): each set whole, every
 * question with its answer and explanation, for the Admin to keep the ones
 * to stay and delete the rest. Also where that check is started.
 */
class ApprovedDuplicates extends Page implements HasTable
{
    use InteractsWithTable;
    use NotifiesValidationFailures;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.approved-duplicates';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare2Stack;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $title = 'Duplicates among approved questions';

    public static function getNavigationBadge(): ?string
    {
        return (string) self::waitingCount();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return self::waitingCount() > 0 ? 'danger' : 'gray';
    }

    private static function waitingCount(): int
    {
        return once(fn (): int => ApprovedDuplicateSet::query()->awaitingAdmin()->count());
    }

    /**
     * The running check, for the bar above the sets.
     *
     * @return array{done: int, total: int}|null
     */
    public function getProgress(): ?array
    {
        return ApprovedDuplicateSweep::progress();
    }

    protected function getHeaderActions(): array
    {
        return [$this->sweepAction()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ApprovedDuplicateSet::query()->awaitingAdmin()->with([
                'chapter.classSubject.academicClass',
                'chapter.classSubject.subject',
                'members.question' => fn ($question) => $question
                    ->withCount('exams')
                    ->with(['cqParts', 'stimulus', 'topic:id,name', 'creator:id,name']),
            ]))
            ->defaultSort('id')
            ->emptyStateHeading(__('No duplicates waiting among the approved questions'))
            ->columns([CardColumn::make('filament.pages.approved-duplicates.card')])
            ->filters([
                SelectFilter::make('academic_class_id')
                    ->label(__('Class'))
                    ->options(fn (): array => AcademicClass::options())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $classId) => $query->whereHas('chapter.classSubject', fn (Builder $classSubject) => $classSubject->where('academic_class_id', $classId)),
                    )),
            ])
            ->recordActions([
                $this->decideAction(),
                $this->notDuplicatesAction(),
            ]);
    }

    private function sweepAction(): Action
    {
        return Action::make('sweep')
            ->label(__('Look for duplicates'))
            ->icon('heroicon-o-magnifying-glass')
            ->modalDescription(__('The approved questions are compared with each other in the background, chapter by chapter; the sets found show up here. Questions you have kept here are not looked through again. Leave the class empty for the whole question bank.'))
            ->schema([
                ContentHierarchySchema::classSelect()->afterStateUpdated(fn ($set) => $set('class_subject_id', null)),
                ContentHierarchySchema::subjectSelect()->afterStateUpdated(fn ($set) => $set('chapter_id', null)),
                ContentHierarchySchema::chapterSelect(),
            ])
            ->action(function (array $data): void {
                $chapters = ApprovedDuplicateSweep::queue(
                    Auth::user(),
                    filled($data['class_subject_id'] ?? null) ? (int) $data['class_subject_id'] : null,
                    filled($data['chapter_id'] ?? null) ? (int) $data['chapter_id'] : null,
                );

                Notification::make()
                    ->title($chapters > 0
                        ? __(':count chapters will be looked through in the background.', ['count' => $chapters])
                        : __('There is no approved question left to look through.'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Which to keep — the one on the most exams ticked to begin with.
     */
    private function decideAction(): Action
    {
        return Action::make('decide')
            ->label(__('Approve & delete the rest'))
            ->color('danger')
            ->icon('heroicon-o-check-badge')
            ->button()
            ->modalHeading(__('Which questions stay?'))
            ->modalDescription(__('The ticked ones stay and are not looked through again; every other one is deleted for good. One already on an exam or in the pay of a Staff member is rejected instead, so that exam and that pay stay as they are.'))
            ->fillForm(fn (ApprovedDuplicateSet $record): array => ['kept' => [ApprovedDuplicateSweep::suggestedToKeep($record->members)]])
            ->schema(fn (ApprovedDuplicateSet $record): array => [
                CheckboxList::make('kept')
                    ->label(__('Keep'))
                    ->options($record->members->mapWithKeys(fn (ApprovedDuplicateSetMember $member, int $index): array => [
                        $member->question_id => ($index + 1).'. '.Str::limit(trim(html_entity_decode(strip_tags((string) $member->question->question_text))), 70)
                            .' · '.__(':count exams', ['count' => $member->question->exams_count]),
                    ])->all())
                    ->required(),
            ])
            ->action(function (ApprovedDuplicateSet $record, array $data): void {
                $this->rescueWithNotification(function () use ($record, $data): void {
                    $counts = ApprovedDuplicateSweep::resolve($record, array_map('intval', $data['kept']), Auth::user());

                    $this->decided($counts);
                });
            });
    }

    private function notDuplicatesAction(): Action
    {
        return Action::make('notDuplicates')
            ->label(__('They are not duplicates'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalDescription(__('Every question of the set stays and is not looked through again.'))
            ->action(function (ApprovedDuplicateSet $record): void {
                $this->rescueWithNotification(function () use ($record): void {
                    $this->decided(ApprovedDuplicateSweep::resolve($record, $record->members->pluck('question_id')->all(), Auth::user()));
                });
            });
    }

    /**
     * @param  array{kept: int, deleted: int, rejected: int}  $counts
     */
    private function decided(array $counts): void
    {
        Notification::make()
            ->title(__(':kept kept, :deleted deleted for good', ['kept' => $counts['kept'], 'deleted' => $counts['deleted']]))
            ->body($counts['rejected'] > 0 ? __(':count already on an exam or in a pay — rejected instead.', ['count' => $counts['rejected']]) : null)
            ->success()
            ->send();

        $this->dispatch('refresh-sidebar');
    }
}

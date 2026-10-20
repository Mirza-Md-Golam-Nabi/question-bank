<?php

namespace App\Filament\Support\Pages;

use App\Enums\PaperSourceType;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\Board;
use App\Models\BoardQuestionPaper;
use App\Services\PlanGrant;
use App\Services\SubscriptionLimitService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The approved past papers — board, medical, university… — for a Teacher
 * to set as an exam and a Student to sit, each paper whole (CLAUDE.md rule
 * 5: never mixed into the question pool). Shared by both panels; a panel's
 * own page says only what can be done with a paper (paperActions()).
 *
 * Which papers the user's plans reach is marked on each row from plans read
 * once for the whole page; the plans are asked again, and the paper counted,
 * the moment one is used (PastPaperExamService).
 */
abstract class PastPapersPage extends Page implements HasTable
{
    use InteractsWithTable;
    use NotifiesValidationFailures;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.past-papers';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $title = 'Past papers';

    /**
     * The user's plans, read once for the whole list.
     *
     * @var array<int, PlanGrant>|null
     */
    private ?array $grants = null;

    /**
     * What a user of this panel can do with a paper.
     *
     * @return array<int, Action>
     */
    abstract protected function paperActions(): array;

    /**
     * Whether this panel's users see a paper's MCQs (and the answers) before
     * using it — a teacher choosing what to set does; a student only meets
     * them in the exam itself.
     */
    abstract protected function showsMcqsInPreview(): bool;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => BoardQuestionPaper::query()
                ->approved()
                ->with(['board', 'classSubject.academicClass', 'classSubject.subject'])
                ->withCount(['mcqQuestions', 'cqQuestions']))
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('board.short_name')
                    ->label(__('Source'))
                    ->badge()
                    ->state(fn (BoardQuestionPaper $record): string => $record->board->display_short_name)
                    ->description(fn (BoardQuestionPaper $record): string => $record->board->display_name),
                TextColumn::make('year')
                    ->sortable()
                    ->description(fn (BoardQuestionPaper $record): ?string => filled($record->unit) ? __(':unit unit', ['unit' => $record->unit]) : null),
                TextColumn::make('classSubject.subject.name')
                    ->label(__('Class · Subject'))
                    ->state(fn (BoardQuestionPaper $record): ?string => $record->classSubject
                        ? "{$record->classSubject->academicClass->name} · {$record->classSubject->subject->display_name}"
                        : null)
                    ->placeholder('—'),
                TextColumn::make('mcq_questions_count')
                    ->label(__('Questions'))
                    ->state(fn (BoardQuestionPaper $record): string => collect([
                        $record->mcq_questions_count ? "{$record->mcq_questions_count} MCQ" : null,
                        $record->cq_questions_count ? "{$record->cq_questions_count} CQ" : null,
                    ])->filter()->join(' · '))
                    ->description(fn (BoardQuestionPaper $record): string => __(':minutes minutes', ['minutes' => $record->duration_minutes ?: max(1, $record->mcq_questions_count)])),
                IconColumn::make('in_plan')
                    ->label(__('In your plan'))
                    ->state(fn (BoardQuestionPaper $record): bool => $this->isInPlan($record))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedLockClosed)
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('source_type')
                    ->label(__('Kind'))
                    ->options(PaperSourceType::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $type) => $query->whereHas('board', fn (Builder $board) => $board->where('type', $type)))),
                SelectFilter::make('board_id')
                    ->label(__('Source'))
                    ->options(fn (): array => Board::ordered()->get()->mapWithKeys(fn (Board $board) => [$board->id => $board->display_name])->all())
                    ->searchable(),
                SelectFilter::make('class')
                    ->label(__('Class'))
                    ->options(fn (): array => AcademicClass::options())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $classId) => $query->whereHas('classSubject', fn (Builder $classSubject) => $classSubject->where('academic_class_id', $classId)))),
            ])
            ->recordActions([
                $this->previewAction(),
                ...$this->paperActions(),
            ]);
    }

    /**
     * The paper's questions to read: a teacher sees all of it with the
     * answers; a student its CQs with their answers (the MCQs are for
     * sitting).
     */
    protected function previewAction(): Action
    {
        $withMcqs = $this->showsMcqsInPreview();

        return Action::make('preview')
            ->label($withMcqs ? __('View paper') : __('View CQ'))
            ->tooltip($withMcqs ? __('View paper') : __('View CQ'))
            ->icon(Heroicon::OutlinedEye)
            ->iconButton()
            ->color('gray')
            ->visible(fn (BoardQuestionPaper $record): bool => $withMcqs || $record->cq_questions_count > 0)
            ->modalHeading(fn (BoardQuestionPaper $record): string => $record->title())
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalContent(fn (BoardQuestionPaper $record): View => view('filament.support.questions.question-list', [
                'mcqQuestions' => $withMcqs ? $record->mcqQuestions : collect(),
                'cqQuestions' => $record->cqQuestions()->with('cqParts')->get(),
            ]));
    }

    /**
     * Whether the user's plans reach this paper at all (how many are left
     * is checked when one is used).
     */
    protected function isInPlan(BoardQuestionPaper $paper): bool
    {
        $this->grants ??= app(SubscriptionLimitService::class)->grantsFor(Auth::user());

        return SubscriptionLimitService::grantsAllowPastPaper($this->grants, $paper);
    }
}

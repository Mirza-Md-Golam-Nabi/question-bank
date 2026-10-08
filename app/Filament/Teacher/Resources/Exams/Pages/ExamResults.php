<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Services\ExamResultSheet;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * Who sat one of the teacher's exams and how they did, ranked by marks —
 * with a button to the printable sheet (PDF / image). The exam is resolved
 * through ExamResource's own query, so a teacher can only ever open the
 * results of an exam they created.
 *
 * @property-read Exam $record
 */
class ExamResults extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected const ROWS_PER_PAGE = 50;

    protected static string $resource = ExamResource::class;

    protected string $view = 'filament.teacher.resources.exams.exam-results';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Results').' — '.$this->getRecord()->title;
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ExamResource::getUrl('index') => ExamResource::getPluralModelLabel(),
            __('Results'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label(__('Back to exams'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => ExamResource::getUrl('index')),
            Action::make('questionAnalysis')
                ->label(__('Question analysis'))
                ->icon(Heroicon::OutlinedChartBar)
                ->color('gray')
                ->url(fn (): string => ExamQuestionAnalysis::getUrl(['record' => $this->getRecord()]))
                ->disabled(fn (): bool => $this->rows->isEmpty()),
            Action::make('print')
                ->label(__('Print / PDF / Image'))
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (): string => route('filament.teacher.exams.results.print', $this->getRecord()))
                ->openUrlInNewTab()
                ->disabled(fn (): bool => $this->rows->isEmpty()),
        ];
    }

    /**
     * The eye button of a result row: that one student's paper, with what
     * they chose on each question beside the correct answer. The teacher
     * always sees the answers here, released to students or not.
     */
    public function viewAnswersAction(): Action
    {
        return Action::make('viewAnswers')
            ->slideOver()
            // Whose paper it is (name and phone/email) is the first line of
            // the panel itself, the same line the student sees on theirs.
            ->modalHeading(__('View answers'))
            ->modalContent(fn (array $arguments) => view('filament.teacher.resources.exams.partials.attempt-answers', [
                'attempt' => app(ExamResultSheet::class)->attemptFor($this->getRecord(), $arguments['attempt'] ?? 0),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    /**
     * Every ranked participant — the ranking has to be worked out over all
     * of them before any one page of it can be shown.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rows(): Collection
    {
        return app(ExamResultSheet::class)->rowsFor($this->getRecord());
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function pageOfRows(): LengthAwarePaginator
    {
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $this->rows->forPage($page, self::ROWS_PER_PAGE)->values(),
            $this->rows->count(),
            self::ROWS_PER_PAGE,
            $page,
        );
    }

    #[Computed]
    public function pendingCount(): int
    {
        return app(ExamResultSheet::class)->pendingCountFor($this->getRecord());
    }
}

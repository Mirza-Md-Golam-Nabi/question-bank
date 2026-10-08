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
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Question by question, how many students answered an exam's questions
 * right and wrong — the hardest question for the class first. Like
 * ExamResults, only ever opens for the teacher who created the exam.
 *
 * @property-read Exam $record
 */
class ExamQuestionAnalysis extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ExamResource::class;

    protected string $view = 'filament.teacher.resources.exams.exam-question-analysis';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Question analysis').' — '.$this->getRecord()->title;
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ExamResource::getUrl('index') => ExamResource::getPluralModelLabel(),
            ExamResults::getUrl(['record' => $this->getRecord()]) => __('Results'),
            __('Question analysis'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('results')
                ->label(__('Back to results'))
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => ExamResults::getUrl(['record' => $this->getRecord()])),
        ];
    }

    /**
     * "Who got this wrong": the students behind one question's wrong
     * count. Looked up only when the teacher opens it, for that question
     * alone — the page itself carries just the counts.
     */
    public function wrongStudentsAction(): Action
    {
        return Action::make('wrongStudents')
            ->modalHeading(__('Students who answered this wrongly'))
            ->modalContent(fn (array $arguments) => view('filament.teacher.resources.exams.partials.wrong-students', [
                'students' => app(ExamResultSheet::class)->wrongAnswersFor($this->getRecord(), (int) ($arguments['question'] ?? 0)),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function stats(): Collection
    {
        return app(ExamResultSheet::class)->questionStatsFor($this->getRecord());
    }
}

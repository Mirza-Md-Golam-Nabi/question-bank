<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Filament\Student\Pages\GeneratePracticeExam;
use App\Filament\Support\Widgets\Performance\Concerns\ReadsStudentPerformance;
use App\Services\PerformanceRate;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * A student's rate in each subject, weakest first, with how much of the
 * subject's questions they have had — and, on their own dashboard, a way
 * straight into practising it.
 */
class SubjectPerformanceWidget extends TableWidget
{
    use ReadsStudentPerformance;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->performance()
                ->subjectRates($this->performanceScope())
                ->map(fn (array $row): array => [
                    '__key' => $row['class_subject']->id,
                    'class_subject_id' => $row['class_subject']->id,
                    'academic_class_id' => $row['class_subject']->academic_class_id,
                    'subject' => $row['class_subject']->label(),
                    'percent' => $row['rate']->percent(),
                    'correct' => $row['rate']->correct,
                    'wrong' => $row['rate']->wrong(),
                    'blank' => $row['rate']->blank,
                    'questions' => $row['rate']->questions,
                    'coverage' => $row['coverage'],
                ])
                ->all())
            ->heading(__('By subject'))
            ->description(__('The share of questions answered correctly in each subject (latest answer each), weakest first.'))
            ->paginated(false)
            ->emptyStateHeading(__('No exams yet'))
            ->emptyStateDescription(__('Subjects appear here once exams in them have been handed in.'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->columns([
                TextColumn::make('subject')->label(__('Subject')),
                TextColumn::make('percent')
                    ->label(__('Correct'))
                    ->formatStateUsing(fn (?int $state): string => "{$state}%")
                    ->placeholder(__('More data needed'))
                    ->badge()
                    ->color(fn (?int $state): string => PerformanceRate::colorFor($state))
                    ->alignCenter(),
                TextColumn::make('questions')
                    ->label(__('Questions'))
                    ->description(fn (array $record): string => __(':correct right · :wrong wrong · :blank blank', [
                        'correct' => $record['correct'],
                        'wrong' => $record['wrong'],
                        'blank' => $record['blank'],
                    ]))
                    ->alignCenter(),
                TextColumn::make('coverage')
                    ->label(__('Questions seen'))
                    ->tooltip(__("The share of the subject's approved MCQs had at least once."))
                    ->formatStateUsing(fn (?int $state): string => "{$state}%")
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->recordActions([
                Action::make('practise')
                    ->label(__('Practise'))
                    ->icon(Heroicon::OutlinedPlay)
                    ->button()
                    ->size('sm')
                    // Practice is always in the student's own class.
                    ->visible(fn (array $record): bool => $this->studentId === null && $record['academic_class_id'] === Auth::user()->academic_class_id)
                    ->url(fn (array $record): string => GeneratePracticeExam::getUrl(['subject' => $record['class_subject_id']], panel: 'student')),
            ]);
    }
}

<?php

namespace App\Filament\Support\Widgets\Performance;

use App\Filament\Support\Widgets\Performance\Concerns\ReadsStudentPerformance;
use App\Services\PerformanceRate;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * A student's rate in each chapter, weakest first.
 */
class ChapterPerformanceWidget extends TableWidget
{
    use ReadsStudentPerformance;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->performance()
                ->chapterRates($this->performanceScope())
                ->map(fn (array $row): array => [
                    '__key' => $row['chapter']->id,
                    'subject' => $row['chapter']->classSubject?->subject?->short_label,
                    'chapter' => $row['chapter']->name,
                    'percent' => $row['rate']->percent(),
                    'questions' => $row['rate']->questions,
                    'correct' => $row['rate']->correct,
                    'wrong' => $row['rate']->wrong(),
                    'blank' => $row['rate']->blank,
                ])
                ->all())
            ->heading(__('By chapter'))
            ->description(__('The share of questions answered correctly in each chapter (latest answer each), weakest first.'))
            ->paginated(false)
            ->emptyStateHeading(__('No exams yet'))
            ->emptyStateDescription(__('Chapters appear here once exams with their questions have been handed in.'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->columns([
                TextColumn::make('subject')->label(__('Subject')),
                TextColumn::make('chapter')->label(__('Chapter')),
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
            ]);
    }
}

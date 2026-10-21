<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Widgets\Concerns\ReadsClassPerformance;
use App\Services\PerformanceRate;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * The chapters the class answers worst on the teacher's exams — what may
 * be worth teaching again.
 */
class TeacherClassWeakChaptersWidget extends TableWidget
{
    use ReadsClassPerformance;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->classPerformance()
                ->weakChapters(Auth::user(), $this->classSubjectId())
                ->map(fn (array $row): array => [
                    '__key' => $row['chapter']->id,
                    'subject' => $row['chapter']->classSubject?->subject?->short_label,
                    'chapter' => $row['chapter']->name,
                    'percent' => $row['rate']->percent(),
                    'questions' => $row['rate']->questions,
                    'blank' => $row['rate']->blank,
                ])
                ->all())
            ->heading(__("The class's weak chapters"))
            ->description(__('Where answers on your exams are most often wrong or blank — worth going over again.'))
            ->paginated(false)
            ->emptyStateHeading(__('Not enough answers yet'))
            ->emptyStateDescription(__('Chapters appear here once enough of their questions have been answered on your exams.'))
            ->emptyStateIcon(Heroicon::OutlinedChartBar)
            ->columns([
                TextColumn::make('chapter')
                    ->label(__('Chapter'))
                    ->description(fn (array $record): ?string => $record['subject']),
                TextColumn::make('percent')
                    ->label(__('Correct'))
                    ->formatStateUsing(fn (?int $state): string => "{$state}%")
                    ->badge()
                    ->color(fn (?int $state): string => PerformanceRate::colorFor($state))
                    ->alignCenter(),
                TextColumn::make('questions')
                    ->label(__('Answers'))
                    ->description(fn (array $record): string => __(':blank blank', ['blank' => $record['blank']]))
                    ->alignCenter(),
            ]);
    }
}

<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\StudentPerformanceReport;
use App\Filament\Teacher\Widgets\Concerns\ReadsClassPerformance;
use App\Models\BillingSetting;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * The students whose scores keep falling on the teacher's exams, or who
 * have stopped sitting them — the Admin sets what counts as either.
 */
class TeacherStudentsNeedingAttentionWidget extends TableWidget
{
    use ReadsClassPerformance;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $settings = BillingSetting::current();

        return $table
            ->records(fn (): array => $this->classPerformance()
                ->needingAttention(Auth::user(), $this->classSubjectId())
                ->take(10)
                ->map(fn (array $row): array => [
                    '__key' => $row['student']->id,
                    'name' => $row['student']->name,
                    'reasons' => array_values(array_filter([
                        $row['falling'] ? __('Score falling') : null,
                        $row['inactive'] ? __('Missing exams') : null,
                    ])),
                    'last_sat_at' => $row['last_sat_at'],
                ])
                ->all())
            ->heading(__('Needs attention'))
            ->description(__('Score down :falling exams in a row, or no exam for :days days while you have given new ones.', [
                'falling' => $settings->performance_falling_exams,
                'days' => $settings->performance_inactive_days,
            ]))
            ->paginated(false)
            ->recordUrl(fn (array $record): string => StudentPerformanceReport::getUrl(['student' => $record['__key']], panel: 'teacher'))
            ->emptyStateHeading(__('Nobody needs attention'))
            ->emptyStateDescription(__('Every student is keeping up, as far as your exams show.'))
            ->emptyStateIcon(Heroicon::OutlinedFaceSmile)
            ->columns([
                TextColumn::make('name')->label(__('Student')),
                TextColumn::make('reasons')
                    ->label(__('Why'))
                    ->badge()
                    ->color('danger'),
                TextColumn::make('last_sat_at')
                    ->label(__('Last exam'))
                    ->localDate(),
            ]);
    }
}

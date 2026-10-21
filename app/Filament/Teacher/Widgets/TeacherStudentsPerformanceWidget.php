<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Pages\StudentPerformanceReport;
use App\Filament\Teacher\Widgets\Concerns\ReadsClassPerformance;
use App\Models\User;
use App\Services\PerformanceRate;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * The logged-in students who have sat the teacher's exams: how many, their
 * average, whether their latest score went up or down — each leading to
 * that student's performance on the teacher's exams.
 */
class TeacherStudentsPerformanceWidget extends TableWidget
{
    use ReadsClassPerformance;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    /**
     * Up or down for the students on the page shown, read once for all.
     *
     * @var array<int, int>|null
     */
    private ?array $directions = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->classPerformance()->students(Auth::user(), $this->classSubjectId()))
            ->heading(__('My students'))
            ->description(__('Logged-in students who have sat your exams (guests are not listed, but count in the class figures above).'))
            ->defaultSort('last_sat_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->recordUrl(fn (User $record): string => StudentPerformanceReport::getUrl(['student' => $record->id], panel: 'teacher'))
            ->emptyStateHeading(__('No students yet'))
            ->emptyStateDescription(__('Students appear here once they have logged in and sat one of your exams.'))
            ->emptyStateIcon(Heroicon::OutlinedUsers)
            ->columns([
                TextColumn::make('name')
                    ->label(__('Student'))
                    ->searchable(),
                TextColumn::make('exams_count')
                    ->label(__('Exams'))
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('average_score')
                    ->label(__('Average score'))
                    ->formatStateUsing(fn ($state): string => round((float) $state).'%')
                    ->badge()
                    ->color(fn ($state): string => PerformanceRate::colorFor($state === null ? null : (int) round((float) $state)))
                    ->placeholder('—')
                    ->sortable()
                    ->alignCenter(),
                IconColumn::make('direction')
                    ->label(__('Latest exam'))
                    ->state(fn (User $record): int => $this->directionOf($record))
                    ->icon(fn (int $state): string => match ($state) {
                        1 => 'heroicon-m-arrow-trending-up',
                        -1 => 'heroicon-m-arrow-trending-down',
                        default => 'heroicon-m-minus',
                    })
                    ->color(fn (int $state): string => match ($state) {
                        1 => 'success',
                        -1 => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (int $state): string => match ($state) {
                        1 => __('Scored higher than on the exam before'),
                        -1 => __('Scored lower than on the exam before'),
                        default => __('No change, or only one exam so far'),
                    })
                    ->alignCenter(),
                TextColumn::make('last_sat_at')
                    ->label(__('Last exam'))
                    ->localDateTime()
                    ->sortable(),
            ]);
    }

    private function directionOf(User $student): int
    {
        $this->directions ??= $this->classPerformance()->directions(
            Auth::user(),
            $this->getTableRecords()->pluck('id')->all(),
            $this->classSubjectId(),
        );

        return $this->directions[$student->id] ?? 0;
    }
}

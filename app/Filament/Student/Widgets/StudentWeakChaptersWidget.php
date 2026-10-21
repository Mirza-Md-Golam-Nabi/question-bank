<?php

namespace App\Filament\Student\Widgets;

use App\Filament\Student\Pages\PractiseMistakes;
use App\Filament\Support\SubjectColumn;
use App\Models\Chapter;
use App\Services\StudentMistakeTracker;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * "Weak chapters" on the student's dashboard: the chapters where most of
 * their mistakes are waiting to be practised — how many, and what share of
 * the chapter's questions they have sat — with a way straight into
 * practising them. The mistakes themselves are StudentMistakeTracker's.
 */
class StudentWeakChaptersWidget extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $mistakes = app(StudentMistakeTracker::class);
        $practisable = fn (Builder $questions): Builder => $mistakes->practisableQuestions(Auth::user(), $questions);
        $sat = fn (Builder $questions): Builder => $mistakes->questionsSat(Auth::user(), $questions);

        return $table
            ->query(
                Chapter::query()
                    ->whereHas('questions', $practisable)
                    ->withCount(['questions as mistakes_count' => $practisable, 'questions as sat_count' => $sat])
                    ->orderByDesc('mistakes_count')
                    ->limit(5)
            )
            ->heading(__('Weak chapters'))
            ->description(__('Where the questions you got wrong are waiting to be practised.'))
            ->paginated(false)
            ->emptyStateHeading(__('No mistakes to practise'))
            ->emptyStateDescription(__('Questions you get wrong in an exam will be collected here.'))
            ->emptyStateIcon(Heroicon::OutlinedCheckBadge)
            ->headerActions([
                Action::make('practiseMistakes')
                    ->label(__('Practise my mistakes'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->url(PractiseMistakes::getUrl(panel: 'student')),
            ])
            ->columns([
                SubjectColumn::make('classSubject.subject'),
                TextColumn::make('name')->label(__('Chapter')),
                TextColumn::make('mistakes_count')->label(__('Mistakes'))->badge()->color('danger')->alignCenter(),
                // How bad: the share of the chapter's questions sat that are
                // still mistakes — 3 of 5 is 60%.
                TextColumn::make('mistake_percent')
                    ->label(__('Mistakes (%)'))
                    ->state(fn (Chapter $record): int => StudentMistakeTracker::mistakePercent((int) $record->mistakes_count, (int) $record->sat_count))
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->description(fn (Chapter $record): string => __(':mistakes of :sat questions', ['mistakes' => $record->mistakes_count, 'sat' => $record->sat_count]))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 50 => 'danger',
                        $state >= 25 => 'warning',
                        default => 'gray',
                    })
                    ->alignCenter(),
            ]);
    }
}

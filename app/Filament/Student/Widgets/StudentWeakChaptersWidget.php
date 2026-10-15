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
 * their mistakes are waiting to be practised, with a way straight into
 * practising them. The mistakes themselves are StudentMistakeTracker's.
 */
class StudentWeakChaptersWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $practisable = fn (Builder $questions): Builder => app(StudentMistakeTracker::class)
            ->practisableQuestions(Auth::user(), $questions);

        return $table
            ->query(
                Chapter::query()
                    ->whereHas('questions', $practisable)
                    ->withCount(['questions as mistakes_count' => $practisable])
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
            ]);
    }
}

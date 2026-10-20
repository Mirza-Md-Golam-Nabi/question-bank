<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\SubjectColumn;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Filament\Teacher\Resources\Exams\Tables\ExamsTable;
use App\Models\Exam;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class TeacherRecentExamsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Exam::query()
                    ->where('created_by', Auth::id())
                    ->withCount('attempts')
                    ->latest('created_at')
                    ->limit(8)
            )
            ->heading(__('Recent exams'))
            ->paginated(false)
            // An exam nobody has started opens in the question picker to be
            // edited, as from the exams list; one that has been sat, at its
            // results.
            ->recordUrl(fn (Exam $record): string => $record->attempts_count === 0
                ? SelectQuestions::getUrl(['exam' => $record->id], panel: 'teacher')
                : ExamResults::getUrl(['record' => $record], panel: 'teacher'))
            ->columns([
                // The date goes under the title rather than in a column of
                // its own, so the share link and the results button still
                // fit in this half-width widget.
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (Exam $record): string => $record->created_at->translatedFormat('d M, h:i A')),
                SubjectColumn::make(),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts_count')->label(__('Attempts'))->alignCenter(),
            ])
            // The same two buttons as on the exams list, side by side:
            // straight to the results, and copy the share link.
            ->recordActions([
                ExamsTable::viewQuestionsAction(),
                ExamsTable::resultsAction(),
                ExamsTable::copyShareLinkAction(),
            ]);
    }
}

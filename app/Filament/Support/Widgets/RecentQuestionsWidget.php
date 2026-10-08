<?php

namespace App\Filament\Support\Widgets;

use App\Enums\QuestionStatus;
use App\Models\Question;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * "Recent questions" on the Staff and Teacher dashboards: the logged-in
 * user's own latest questions, each row opening that panel's edit page.
 */
abstract class RecentQuestionsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 1;

    /**
     * Where a row leads — the edit page of this widget's own panel.
     */
    abstract protected function editQuestionUrl(Question $question): string;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Question::query()
                    ->where('created_by', Auth::id())
                    ->where('is_latest', true)
                    ->latest('created_at')
                    ->limit(8)
            )
            ->heading(__('Recent questions'))
            ->paginated(false)
            ->recordUrl(fn (Question $record) => $this->editQuestionUrl($record))
            ->columns([
                TextColumn::make('chapter.classSubject.subject.name')->label(__('Subject')),
                TextColumn::make('chapter.name')->label(__('Chapter')),
                TextColumn::make('question_type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (QuestionStatus $state) => $state->getColor())
                    ->description(fn (Question $record) => $record->status === QuestionStatus::Rejected ? $record->rejection_reason : null),
                TextColumn::make('created_at')->dateTime('d M, h:i A')->label(__('Submitted')),
            ]);
    }
}

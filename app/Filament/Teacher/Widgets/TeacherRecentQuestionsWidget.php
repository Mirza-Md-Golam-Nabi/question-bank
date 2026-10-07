<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\QuestionStatus;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Models\Question;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class TeacherRecentQuestionsWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

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
            ->recordUrl(fn (Question $record) => EditQuestion::getUrl(['record' => $record], panel: 'teacher'))
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

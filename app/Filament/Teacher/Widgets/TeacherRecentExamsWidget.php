<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Teacher\Resources\Exams\Pages\EditExam;
use App\Models\Exam;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

class TeacherRecentExamsWidget extends TableWidget
{
    protected static ?int $sort = 5;

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
            ->recordUrl(fn (Exam $record) => EditExam::getUrl(['record' => $record], panel: 'teacher'))
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('subject.name')->label(__('Subject')),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts_count')->label(__('Attempts')),
                TextColumn::make('share_token')
                    ->label(__('Share link'))
                    ->formatStateUsing(fn (?string $state) => $state ? url("/exam/{$state}") : '—')
                    ->copyable()
                    ->copyMessage(__('Link copied')),
                TextColumn::make('created_at')->dateTime('d M, h:i A')->label(__('Created')),
            ]);
    }
}

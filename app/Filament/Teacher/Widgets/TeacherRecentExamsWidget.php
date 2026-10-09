<?php

namespace App\Filament\Teacher\Widgets;

use App\Filament\Support\SubjectColumn;
use App\Filament\Teacher\Resources\Exams\Pages\EditExam;
use App\Models\Exam;
use Filament\Support\Icons\Heroicon;
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
                SubjectColumn::make(),
                TextColumn::make('status')->badge(),
                TextColumn::make('attempts_count')->label(__('Attempts')),
                // Just a link icon, not the whole URL spelled out: a click
                // copies the link. Blank until the exam has one.
                TextColumn::make('share_token')
                    ->label(__('Share link'))
                    ->formatStateUsing(fn (): string => '')
                    ->icon(Heroicon::OutlinedLink)
                    ->iconColor('primary')
                    ->tooltip(__('Copy share link'))
                    ->alignCenter()
                    ->copyable()
                    ->copyableState(fn (Exam $record): ?string => $record->shareUrl())
                    ->copyMessage(__('Link copied'))
                    ->placeholder('—'),
                TextColumn::make('created_at')->dateTime('d M, h:i A')->label(__('Created')),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Models\Chapter;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    public Chapter|int|string $chapter;

    public function mount(int|string|null $chapter = null): void
    {
        $this->chapter = Chapter::with('classSubject.subject', 'classSubject.academicClass')->findOrFail($chapter);

        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->chapter->name} — Questions";
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(
            fn (Builder $query) => $query->where('chapter_id', $this->chapter->id),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn (): string => QuestionResource::getUrl('create', ['chapter' => $this->chapter->id])),
        ];
    }
}

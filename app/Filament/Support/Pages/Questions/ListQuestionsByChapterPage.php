<?php

namespace App\Filament\Support\Pages\Questions;

use App\Models\Chapter;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The terminal step of the Class → Subject → Chapter drill-down: the
 * question list for one chapter, shared by every panel's QuestionResource.
 * Which rows actually show up is still governed entirely by
 * QuestionResource::getEloquentQuery() (own-only for Staff, own + approved
 * pool for Teacher, everything for Admin) — this page only adds the
 * chapter filter on top.
 */
abstract class ListQuestionsByChapterPage extends ListRecords
{
    public Chapter|int|string $chapter;

    public function mount(int|string|null $chapter = null): void
    {
        $this->chapter = Chapter::with('classSubject.subject', 'classSubject.academicClass')->findOrFail($chapter);

        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->chapter->name} — ".__('Questions');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        $classSubject = $this->chapter->classSubject;

        return [
            static::getResource()::getUrl('index') => __('Classes'),
            static::getResource()::getUrl('subjects', ['class' => $classSubject->academic_class_id]) => $classSubject->academicClass->name,
            static::getResource()::getUrl('chapters', ['class' => $classSubject->academic_class_id, 'classSubject' => $classSubject->id]) => $classSubject->subject->name,
            "{$this->chapter->name} — ".__('Questions'),
        ];
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
                ->url(fn (): string => static::getResource()::getUrl('create', ['chapter' => $this->chapter->id])),
        ];
    }
}

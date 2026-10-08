<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\NavigatesAdjacentChapters;
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
    use NavigatesAdjacentChapters;

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
        $table = parent::table($table)->modifyQueryUsing(
            fn (Builder $query) => $query->where('chapter_id', $this->chapter->id),
        );

        // Every row here shares the one class, subject, and chapter already
        // named in the breadcrumbs, so those columns would only repeat
        // themselves — the topic is what actually differs between rows.
        $table->getColumn('chapter.classSubject.academicClass.name')?->hidden();
        $table->getColumn('chapter.classSubject.subject.name')?->hidden();
        $table->getColumn('chapter.name')?->hidden();

        return $table;
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getAdjacentChapterActions(),
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create', ['chapter' => $this->chapter->id])),
        ];
    }

    protected function getAdjacentChapterUrl(Chapter $chapter): string
    {
        return static::getResource()::getUrl('list', ['chapter' => $chapter->id]);
    }
}

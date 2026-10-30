<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\NavigatesAdjacentChapters;
use App\Filament\Support\Concerns\OrdersOldestOrLatest;
use App\Filament\Support\Concerns\SwitchesCardAndTableView;
use App\Filament\Support\QuestionJsonImportAction;
use App\Filament\Support\StimulusGroupActions;
use App\Models\Chapter;
use App\Models\Topic;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
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
 *
 * Every panel shows each row as the whole question (the question picker's
 * card, with its answer) by default, with a button to switch to the table
 * columns; the row actions, bulk actions, filters and pagination — each
 * panel's own — are the same in both.
 */
abstract class ListQuestionsByChapterPage extends ListRecords
{
    use NavigatesAdjacentChapters;
    use OrdersOldestOrLatest;
    use SwitchesCardAndTableView;

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
            static::getResource()::getUrl('chapters', ['class' => $classSubject->academic_class_id, 'classSubject' => $classSubject->id]) => $classSubject->subject->display_name,
            "{$this->chapter->name} — ".__('Questions'),
        ];
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('chapter_id', $this->chapter->id)
                ->when($this->showsCards(), fn (Builder $query) => $query->with(['topic:id,name', 'cqParts', 'stimulus', 'creator:id,name'])))
            // By what the question says (and its topic) in both views — the
            // card view has no columns of its own to search by.
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search) => $query->matching($search))
            // By ID, the oldest or the latest first (the dropdown in the
            // table's toolbar, beside the search). Never the whole chapter on
            // one page.
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy($query->qualifyColumn('id'), $this->orderDirection()))
            ->pushToolbarActions([$this->orderDropdown()])
            ->paginationPageOptions([10, 25, 50])
            ->pushFilters([
                SelectFilter::make('topic_id')
                    ->label(__('Topic'))
                    ->placeholder(__('All topics'))
                    ->options(Topic::optionsFor($this->chapter->id)),
            ])
            // Open above the list, each applied the moment it is picked.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false);

        // Every row here shares the one class, subject, and chapter already
        // named in the breadcrumbs, so those columns would only repeat
        // themselves — the topic is what actually differs between rows.
        $table->getColumn('chapter.classSubject.academicClass.name')?->hidden();
        $table->getColumn('chapter.classSubject.subject.name')?->hidden();
        $table->getColumn('chapter.name')?->hidden();

        $table->columns($this->showsCards()
            ? [View::make('filament.support.questions.question-table-card')]
            : [TextColumn::make('id')->label(__('ID')), ...$table->getColumnsLayout()]);

        return $table;
    }

    protected function displayUrl(string $display): string
    {
        return static::getResource()::getUrl('list', ['chapter' => $this->chapter->id, 'view' => $display, 'order' => $this->order]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->getAdjacentChapterActions(),
            $this->switchDisplayAction(),
            QuestionJsonImportAction::make($this->chapter),
            StimulusGroupActions::create($this->chapter),
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create', ['chapter' => $this->chapter->id])),
        ];
    }

    protected function getAdjacentChapterUrl(Chapter $chapter): string
    {
        return static::getResource()::getUrl('list', ['chapter' => $chapter->id]);
    }
}

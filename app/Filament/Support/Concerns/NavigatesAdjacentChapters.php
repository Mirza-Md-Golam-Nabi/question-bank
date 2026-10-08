<?php

namespace App\Filament\Support\Concerns;

use App\Models\Chapter;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Icon-only "previous chapter" / "next chapter" header buttons for a page
 * that is scoped to one chapter (its `$chapter` property), so the user can
 * step through a subject's chapters without going back to the chapter grid.
 * The page only decides where each neighbour links to.
 */
trait NavigatesAdjacentChapters
{
    /**
     * URL of this same page for the given neighbouring chapter.
     */
    abstract protected function getAdjacentChapterUrl(Chapter $chapter): string;

    /**
     * @return array<int, Action>
     */
    protected function getAdjacentChapterActions(): array
    {
        return [
            $this->adjacentChapterAction('previousChapter', __('Previous chapter'), Heroicon::OutlinedChevronLeft, isNext: false),
            $this->adjacentChapterAction('nextChapter', __('Next chapter'), Heroicon::OutlinedChevronRight, isNext: true),
        ];
    }

    /**
     * Disabled at either end of the subject's chapter list.
     */
    protected function adjacentChapterAction(string $name, string $label, Heroicon $icon, bool $isNext): Action
    {
        $adjacentChapter = $this->adjacentChapter($isNext);

        return Action::make($name)
            ->label($label)
            ->tooltip($adjacentChapter?->name ?? $label)
            ->icon($icon)
            ->iconButton()
            ->color('gray')
            ->disabled($adjacentChapter === null)
            ->url($adjacentChapter ? $this->getAdjacentChapterUrl($adjacentChapter) : null);
    }

    /**
     * The chapter right after (or before) this one within the same class
     * subject, following the same display order as the chapter card grid.
     */
    protected function adjacentChapter(bool $isNext): ?Chapter
    {
        $operator = $isNext ? '>' : '<';
        $direction = $isNext ? 'asc' : 'desc';

        return Chapter::query()
            ->where('class_subject_id', $this->chapter->class_subject_id)
            ->where(fn (Builder $query) => $query
                ->where('order_index', $operator, $this->chapter->order_index)
                ->orWhere(fn (Builder $query) => $query
                    ->where('order_index', $this->chapter->order_index)
                    ->where('id', $operator, $this->chapter->id)))
            ->orderBy('order_index', $direction)
            ->orderBy('id', $direction)
            ->first();
    }
}

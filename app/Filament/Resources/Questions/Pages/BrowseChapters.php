<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\ManagesOrderedItems;
use App\Filament\Support\Pages\Questions\BrowseChaptersPage;
use App\Models\Chapter;
use Filament\Actions\Action;

class BrowseChapters extends BrowseChaptersPage
{
    use ManagesOrderedItems;

    protected static string $resource = QuestionResource::class;

    public function canManageContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createChapterAction(),
        ];
    }

    public function createChapterAction(): Action
    {
        return $this->createOrderedItemAction('createChapter', __('Add chapter'), Chapter::class, $this->chapterScope(), allowsAddingAnother: true);
    }

    public function editChapterAction(): Action
    {
        return $this->editOrderedItemAction('editChapter', 'chapter', Chapter::class, $this->chapterScope());
    }

    public function deleteChapterAction(): Action
    {
        return $this->deleteOrderedItemAction('deleteChapter', 'chapter', Chapter::class);
    }

    /**
     * Chapters are named and ordered within their class subject.
     *
     * @return array<string, int>
     */
    private function chapterScope(): array
    {
        return ['class_subject_id' => $this->classSubject->id];
    }
}

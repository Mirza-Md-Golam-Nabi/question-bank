<?php

namespace App\Filament\Support\Concerns;

use App\Models\BoardQuestionPaper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Shared by every panel's Create past paper page: one paper for each source
 * picked on the form. A single new paper opens on its own page, where its
 * questions are added; several go back to the list, to be opened one by one.
 */
trait CreatesPapersForSources
{
    private int $createdPaperCount = 0;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $papers = BoardQuestionPaper::createForSources($data['board_ids'], Arr::except($data, 'board_ids'));

        $this->createdPaperCount = $papers->count();

        return $papers->first();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdPaperCount > 1
            ? __(':count past papers created.', ['count' => $this->createdPaperCount])
            : parent::getCreatedNotificationTitle();
    }

    protected function getRedirectUrl(): string
    {
        return $this->createdPaperCount > 1
            ? static::getResource()::getUrl('index')
            : static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\BoardQuestionPaperFormSchema;
use App\Models\BoardQuestionPaper;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by every panel's Create/Edit past paper page: what the details
 * form saves is made consistent first (BoardQuestionPaperFormSchema::
 * normalize()), and a Staff member's change to their rejected paper sends
 * it back for review. The questions themselves are saved one by one by the
 * page's question lists, not here.
 */
trait HandlesBoardQuestionPaperForm
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BoardQuestionPaperFormSchema::normalize($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BoardQuestionPaperFormSchema::normalize($data);
    }

    protected function afterSave(): void
    {
        /** @var BoardQuestionPaper $paper */
        $paper = $this->getRecord();

        $paper->markEditedBy(Auth::user());
    }
}

<?php

namespace App\Jobs;

use App\Services\QuestionDuplicateScreening;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Looks for duplicates among one chapter's questions already there
 * (QuestionDuplicateScreening::screenChapter()).
 */
class ScreenChapterForDuplicates implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $chapterId) {}

    public function handle(): void
    {
        QuestionDuplicateScreening::make()->screenChapter($this->chapterId);
    }
}

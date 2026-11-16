<?php

namespace App\Jobs;

use App\Services\ApprovedDuplicateSweep;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Looks for sets of alike questions among one chapter's approved
 * questions (ApprovedDuplicateSweep::sweepChapter()).
 */
class ScreenApprovedChapterForDuplicates implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $chapterId) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        ApprovedDuplicateSweep::make()->sweepChapter($this->chapterId);
    }
}

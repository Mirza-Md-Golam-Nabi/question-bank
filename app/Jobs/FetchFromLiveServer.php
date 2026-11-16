<?php

namespace App\Jobs;

use App\Models\QuestionSyncRun;
use App\Services\QuestionSyncImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One fetch from the live server (QuestionSyncImporter::run()) — a chapter
 * of hundreds of questions with their images takes a while. Not tried
 * again by itself: a failed run says why, and fetching again carries on.
 */
class FetchFromLiveServer implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $runId) {}

    public function handle(QuestionSyncImporter $importer): void
    {
        $run = QuestionSyncRun::query()->with(['chapter', 'starter'])->find($this->runId);

        if ($run) {
            $importer->run($run);
        }
    }
}

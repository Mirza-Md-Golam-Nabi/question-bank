<?php

namespace App\Jobs;

use App\Services\QuestionDuplicateScreening;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks one new or changed question for duplicates in the background
 * (QuestionDuplicateScreening::screen()). A question saved with its parts,
 * or changed twice before its turn, is checked once.
 */
class ScreenQuestionForDuplicates implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Should the job ever be lost from the queue, the question can still be
     * queued again after this long.
     */
    public int $uniqueFor = 3600;

    public function __construct(public int $questionId) {}

    public function uniqueId(): string
    {
        return (string) $this->questionId;
    }

    public function handle(): void
    {
        QuestionDuplicateScreening::make()->screen($this->questionId);
    }
}

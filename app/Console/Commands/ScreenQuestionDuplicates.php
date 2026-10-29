<?php

namespace App\Console\Commands;

use App\Enums\DuplicateCheckStatus;
use App\Models\Question;
use App\Services\QuestionDuplicateScreening;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('questions:screen-duplicates {--chapter=* : Only these chapters (by id)}')]
#[Description('Look for duplicates among the questions already there, chapter by chapter, here and now rather than on the queue')]
class ScreenQuestionDuplicates extends Command
{
    public function handle(): int
    {
        $chapterIds = collect($this->option('chapter'))->map(fn (string $id): int => (int) $id);

        if ($chapterIds->isEmpty()) {
            $chapterIds = Question::query()->where('is_latest', true)->where('is_private', false)->distinct()->pluck('chapter_id');
        }

        $screening = QuestionDuplicateScreening::make();

        $this->withProgressBar($chapterIds, fn (int $chapterId) => $screening->screenChapter($chapterId));
        $this->newLine();

        $waiting = Question::query()->where('duplicate_status', DuplicateCheckStatus::Suspected)->count();
        $this->info("{$waiting} possible duplicates are waiting for the Admin.");

        return self::SUCCESS;
    }
}

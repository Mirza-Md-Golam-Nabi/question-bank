<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where one fetch from the live server is (App\Models\QuestionSyncRun).
 */
enum QuestionSyncRunStatus: string implements HasLabel
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Queued => __('Waiting to start'),
            self::Running => __('Fetching'),
            self::Done => __('Done'),
            self::Failed => __('Failed'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued, self::Running => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}

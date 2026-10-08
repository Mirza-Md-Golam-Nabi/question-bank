<?php

namespace App\Filament\Staff\Pages;

use App\Enums\QuestionStatus;
use App\Filament\Support\Pages\QuestionStatusBreakdownPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class PendingQuestionsBreakdown extends QuestionStatusBreakdownPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    public function getTitle(): string
    {
        return __('Pending Questions');
    }

    protected function status(): QuestionStatus
    {
        return QuestionStatus::Pending;
    }

    public function emptyText(): string
    {
        return __('No pending questions right now.');
    }

    public function countLabel(int $count): string
    {
        return trans_choice('question pending|questions pending', $count);
    }

    public function cardIcon(): string
    {
        return 'heroicon-o-clock';
    }
}

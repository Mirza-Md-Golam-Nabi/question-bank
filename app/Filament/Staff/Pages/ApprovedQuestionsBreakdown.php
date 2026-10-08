<?php

namespace App\Filament\Staff\Pages;

use App\Enums\QuestionStatus;
use App\Filament\Support\Pages\QuestionStatusBreakdownPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ApprovedQuestionsBreakdown extends QuestionStatusBreakdownPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    public function getTitle(): string
    {
        return __('Approved Questions');
    }

    protected function status(): QuestionStatus
    {
        return QuestionStatus::Approved;
    }

    public function emptyText(): string
    {
        return __('No approved questions yet.');
    }

    public function countLabel(int $count): string
    {
        return trans_choice('question approved|questions approved', $count);
    }

    public function cardIcon(): string
    {
        return 'heroicon-o-check-badge';
    }

    public function paletteOffset(): int
    {
        return 2;
    }
}

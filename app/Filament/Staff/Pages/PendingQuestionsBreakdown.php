<?php

namespace App\Filament\Staff\Pages;

use App\Enums\QuestionStatus;
use App\Filament\Support\Concerns\GroupsQuestionsByClassSubject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Drill-down reached by clicking the "Questions Pending" card on
 * MyEarnings — not a nav item itself, just a detail view.
 */
class PendingQuestionsBreakdown extends Page
{
    use GroupsQuestionsByClassSubject;

    protected string $view = 'filament.staff.pages.pending-questions-breakdown';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'Pending Questions';
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MyEarnings::getUrl(panel: 'staff') => 'My Earnings',
            'Pending Questions',
        ];
    }

    /**
     * @return Collection<int, array{class: string, subject: string, count: int}>
     */
    public function breakdown(): Collection
    {
        return $this->classSubjectBreakdown(QuestionStatus::Pending);
    }
}

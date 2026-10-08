<?php

namespace App\Filament\Support\Pages;

use App\Enums\QuestionStatus;
use App\Filament\Staff\Pages\MyEarnings;
use App\Filament\Support\Concerns\GroupsQuestionsByClassSubject;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * A Staff member's own questions of one status, counted per class and
 * subject — the drill-down behind the "Questions Approved" / "Questions
 * Pending" cards on My Earnings (not a navigation item itself). A page only
 * says which status it is about and how to word it.
 */
abstract class QuestionStatusBreakdownPage extends Page
{
    use GroupsQuestionsByClassSubject;
    use TranslatesPageLabels;

    protected string $view = 'filament.staff.pages.question-status-breakdown';

    protected static bool $shouldRegisterNavigation = false;

    abstract protected function status(): QuestionStatus;

    /**
     * Shown when the staff member has no questions in this status.
     */
    abstract public function emptyText(): string;

    /**
     * The words under a card's number, e.g. "questions approved".
     */
    abstract public function countLabel(int $count): string;

    /**
     * The watermark icon of each card.
     */
    abstract public function cardIcon(): string;

    /**
     * Which colour the card grid starts on (see CardPalette).
     */
    public function paletteOffset(): int
    {
        return 0;
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            MyEarnings::getUrl(panel: 'staff') => __('My Earnings'),
            (string) $this->getTitle(),
        ];
    }

    /**
     * @return Collection<int, array{class: string, subject: string, count: int}>
     */
    public function breakdown(): Collection
    {
        return $this->classSubjectBreakdown($this->status());
    }
}

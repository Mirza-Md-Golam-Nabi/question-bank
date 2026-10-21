<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a plan can switch on beyond which questions it reaches: taking a
 * teacher's exam online through a share link, the printable paper, and
 * sitting (or, for a teacher, setting) a whole past paper as an exam,
 * and the performance analytics (a student's own, a teacher's class) —
 * which only a paid plan ever gives (SubscriptionLimitService::allowsAnalytics()).
 */
enum PlanFeature: string implements HasLabel
{
    case OnlineExam = 'online_exam';
    case Print = 'print';
    case PastPapers = 'past_papers';
    case Analytics = 'analytics';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OnlineExam => __('Online exam (share link)'),
            self::Print => __('Print / PDF'),
            self::PastPapers => __('Past papers (whole-paper exams)'),
            self::Analytics => __('Performance analytics'),
        };
    }
}

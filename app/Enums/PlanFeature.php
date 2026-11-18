<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a plan can switch on beyond which questions it reaches: taking a
 * teacher's exam online through a share link, the printable paper, and
 * sitting (or, for a teacher, setting) a whole past paper as an exam,
 * the performance analytics (a student's own, a teacher's class) —
 * which only a paid plan ever gives (SubscriptionLimitService::allowsAnalytics()) —
 * and checking OMR sheets against an answer key alone, without questions
 * (an OMR check, CLAUDE.md rule 10.12).
 */
enum PlanFeature: string implements HasLabel
{
    case OnlineExam = 'online_exam';
    case Print = 'print';
    case PastPapers = 'past_papers';
    case Analytics = 'analytics';
    case OmrCheck = 'omr_check';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OnlineExam => __('Online exam (share link)'),
            self::Print => __('Print / PDF'),
            self::PastPapers => __('Past papers (whole-paper exams)'),
            self::Analytics => __('Performance analytics'),
            self::OmrCheck => __('OMR check (answer key only)'),
        };
    }
}

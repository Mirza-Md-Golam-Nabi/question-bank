<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a plan can switch on beyond which questions it reaches: taking a
 * teacher's exam online through a share link, the printable paper, and
 * sitting (or, for a teacher, setting) a whole past paper as an exam.
 */
enum PlanFeature: string implements HasLabel
{
    case OnlineExam = 'online_exam';
    case Print = 'print';
    case PastPapers = 'past_papers';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::OnlineExam => __('Online exam (share link)'),
            self::Print => __('Print / PDF'),
            self::PastPapers => __('Past papers (whole-paper exams)'),
        };
    }
}

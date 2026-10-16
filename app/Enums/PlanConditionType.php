<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The kinds of condition the Admin can add to a plan. A plan without a
 * condition of some kind is unrestricted in that respect (no classes
 * condition = every class). Read through App\Services\PlanConditions.
 */
enum PlanConditionType: string implements HasLabel
{
    case Classes = 'classes';
    case Subjects = 'subjects';
    case Difficulties = 'difficulties';
    case Features = 'features';
    case DailyExamLimit = 'daily_exam_limit';
    case MonthlyExamLimit = 'monthly_exam_limit';
    case MaxParticipants = 'max_participants';
    case Seats = 'seats';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Classes => __('Classes'),
            self::Subjects => __('Subjects'),
            self::Difficulties => __('Difficulty'),
            self::Features => __('Features'),
            self::DailyExamLimit => __('Exams per day'),
            self::MonthlyExamLimit => __('Exams per month'),
            self::MaxParticipants => __('Participants per online exam'),
            self::Seats => __('People sharing the plan'),
        };
    }
}

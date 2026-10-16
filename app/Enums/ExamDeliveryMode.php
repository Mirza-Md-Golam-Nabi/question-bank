<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a Teacher exam reaches students (CLAUDE.md rule 10): through the
 * share-link, as a printed question paper, or both. CQ questions have no
 * stored answer and can't be taken online, so they only ever appear on the
 * printed side.
 */
enum ExamDeliveryMode: string implements HasLabel
{
    case Online = 'online';
    case Offline = 'offline';
    case Both = 'both';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Online => __('Online'),
            self::Offline => __('Offline (print)'),
            self::Both => __('Online + Offline'),
        };
    }

    public function includesOnline(): bool
    {
        return $this !== self::Offline;
    }

    public function includesOffline(): bool
    {
        return $this !== self::Online;
    }

    /**
     * What the teacher's plan has to include for an exam taken this way.
     *
     * @return array<int, PlanFeature>
     */
    public function requiredFeatures(): array
    {
        return array_values(array_filter([
            $this->includesOnline() ? PlanFeature::OnlineExam : null,
            $this->includesOffline() ? PlanFeature::Print : null,
        ]));
    }

    /**
     * An online-only exam has nowhere to put a CQ question.
     */
    public function allowsCq(): bool
    {
        return $this->includesOffline();
    }
}

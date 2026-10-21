<?php

namespace App\Filament\Teacher\Widgets\Concerns;

use App\Filament\Support\Widgets\Performance\Concerns\NeedsAnalyticsPlan;
use App\Services\TeacherClassPerformance;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * A widget of the teacher's class performance, of the class + subject
 * picked in the dashboard's filter (all of them when none is).
 */
trait ReadsClassPerformance
{
    use InteractsWithPageFilters;
    use NeedsAnalyticsPlan;

    protected function classSubjectId(): ?int
    {
        $id = $this->pageFilters['class_subject_id'] ?? null;

        return filled($id) ? (int) $id : null;
    }

    protected function classPerformance(): TeacherClassPerformance
    {
        return app(TeacherClassPerformance::class);
    }
}

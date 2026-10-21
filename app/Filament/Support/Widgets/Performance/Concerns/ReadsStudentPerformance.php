<?php

namespace App\Filament\Support\Widgets\Performance\Concerns;

use App\Enums\PerformanceSource;
use App\Models\User;
use App\Services\PerformanceScope;
use App\Services\StudentPerformance;
use App\Services\TeacherClassPerformance;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Auth;

/**
 * A widget of one student's performance, which shows either:
 *
 * - on the student's own dashboard, their own figures, of the kind of
 *   exam picked in the dashboard's filter; or
 * - on a teacher's page about one of their students (`studentId` given),
 *   that student's figures from the teacher's own exams only — and only a
 *   student who has sat one of them.
 */
trait ReadsStudentPerformance
{
    use InteractsWithPageFilters;
    use NeedsAnalyticsPlan;

    /**
     * Set by the teacher's page about a student; null on the student's own dashboard.
     */
    public ?int $studentId = null;

    protected function performanceScope(): PerformanceScope
    {
        $viewer = Auth::user();

        if ($this->studentId === null) {
            return PerformanceScope::ownView($viewer, PerformanceSource::tryFrom((string) ($this->pageFilters['source'] ?? '')) ?? PerformanceSource::All);
        }

        $student = User::query()->find($this->studentId);

        abort_unless($student && app(TeacherClassPerformance::class)->hasStudent($viewer, $student), 404);

        return PerformanceScope::teacherView($student, $viewer);
    }

    protected function performance(): StudentPerformance
    {
        return app(StudentPerformance::class);
    }
}

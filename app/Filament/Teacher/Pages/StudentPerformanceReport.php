<?php

namespace App\Filament\Teacher\Pages;

use App\Filament\Support\Widgets\Performance\ChapterPerformanceWidget;
use App\Filament\Support\Widgets\Performance\DifficultyChartWidget;
use App\Filament\Support\Widgets\Performance\PerformanceStatsWidget;
use App\Filament\Support\Widgets\Performance\ScoreTrendChartWidget;
use App\Filament\Support\Widgets\Performance\SubjectPerformanceWidget;
use App\Filament\Teacher\Widgets\TeacherAnalyticsLockedWidget;
use App\Models\User;
use App\Services\TeacherClassPerformance;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * One student's performance, as a teacher sees it: from that teacher's
 * own exams only (PerformanceScope::teacherView()), and only for a student
 * who has sat one of them. Opened from the dashboard's student lists.
 */
class StudentPerformanceReport extends Page
{
    protected static ?string $slug = 'students/{student}/performance';

    protected static bool $shouldRegisterNavigation = false;

    public User $student;

    public function mount(User $student): void
    {
        abort_unless(app(TeacherClassPerformance::class)->hasStudent(Auth::user(), $student), 404);

        $this->student = $student;
    }

    public function getTitle(): string|Htmlable
    {
        return __(":name's performance", ['name' => $this->student->name]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('From your exams only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['studentId' => $this->student->id];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TeacherAnalyticsLockedWidget::class,
            PerformanceStatsWidget::class,
            ScoreTrendChartWidget::class,
            DifficultyChartWidget::class,
            SubjectPerformanceWidget::class,
            ChapterPerformanceWidget::class,
        ];
    }
}

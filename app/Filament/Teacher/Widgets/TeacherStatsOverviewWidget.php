<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Filament\Support\StatusBadgePills;
use App\Filament\Support\Widgets\DashboardStat;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\SubscriptionLimitService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TeacherStatsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $teacher = Auth::user();
        $questionCounts = DashboardStat::questionCountsFor($teacher->id);

        $examCounts = Exam::where('created_by', $teacher->id)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $draftExams = (int) ($examCounts[ExamStatus::Draft->value] ?? 0);
        $publishedExams = (int) ($examCounts[ExamStatus::Published->value] ?? 0)
            + (int) ($examCounts[ExamStatus::Ongoing->value] ?? 0)
            + (int) ($examCounts[ExamStatus::Completed->value] ?? 0);

        $totalAttempts = ExamAttempt::whereHas(
            'exam',
            fn ($query) => $query->where('created_by', $teacher->id)
        )->count();

        $monthlyLimit = app(SubscriptionLimitService::class)->monthlyExamLimitFor($teacher);
        $usedThisMonth = Exam::createdThisMonthBy($teacher, ExamType::TeacherExam)->count();

        return [
            DashboardStat::totalQuestions($questionCounts),
            DashboardStat::approvalRate($questionCounts),

            DashboardStat::make(__('Total exams'), $draftExams + $publishedExams, 'heroicon-o-clipboard-document-list', 'info')
                ->description(StatusBadgePills::make([
                    ['label' => __('Draft'), 'count' => $draftExams, 'dotClass' => 'bg-gray-300'],
                    ['label' => __('Published'), 'count' => $publishedExams, 'dotClass' => 'bg-emerald-300'],
                ])),

            DashboardStat::make(
                __('Exam usage this month'),
                $monthlyLimit === null ? "{$usedThisMonth} (".__('Unlimited').')' : "{$usedThisMonth} / {$monthlyLimit}",
                'heroicon-o-calendar-days',
                match (true) {
                    $monthlyLimit === null => 'success',
                    $usedThisMonth >= $monthlyLimit => 'danger',
                    $usedThisMonth >= $monthlyLimit * 0.7 => 'warning',
                    default => 'success',
                },
            ),

            DashboardStat::make(__('Total attempts'), $totalAttempts, 'heroicon-o-users', 'warning')
                ->description(__('Across all exams')),
        ];
    }
}

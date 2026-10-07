<?php

namespace App\Filament\Teacher\Widgets;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionStatus;
use App\Filament\Support\StatusBadgePills;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
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

        $questionCounts = Question::where('created_by', $teacher->id)
            ->where('is_latest', true)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pending = (int) ($questionCounts[QuestionStatus::Pending->value] ?? 0);
        $approved = (int) ($questionCounts[QuestionStatus::Approved->value] ?? 0);
        $rejected = (int) ($questionCounts[QuestionStatus::Rejected->value] ?? 0);
        $decided = $approved + $rejected;
        $approvalRate = $decided > 0 ? round($approved / $decided * 100, 1) : null;

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

        $limitService = app(SubscriptionLimitService::class);
        $monthlyLimit = $limitService->monthlyExamLimitFor($teacher);
        $usedThisMonth = Exam::createdThisMonthBy($teacher, ExamType::TeacherExam)->count();

        return [
            Stat::make(__('Total questions'), $pending + $approved + $rejected)
                ->description(StatusBadgePills::questionStatus($pending, $approved, $rejected))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-slate-500 to-slate-600 [&_*]:!text-white']),

            Stat::make(__('Approval rate'), $approvalRate === null ? '—' : number_format($approvalRate, 1).'%')
                ->icon('heroicon-o-chart-bar')
                ->color(match (true) {
                    $approvalRate === null => 'gray',
                    $approvalRate >= 70 => 'success',
                    $approvalRate >= 40 => 'warning',
                    default => 'danger',
                })
                ->extraAttributes([
                    'class' => match (true) {
                        $approvalRate === null => 'bg-gradient-to-br from-gray-400 to-gray-500 [&_*]:!text-white',
                        $approvalRate >= 70 => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white',
                        $approvalRate >= 40 => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white',
                        default => 'bg-gradient-to-br from-rose-500 to-rose-600 [&_*]:!text-white',
                    },
                ]),

            Stat::make(__('Total exams'), $draftExams + $publishedExams)
                ->description(StatusBadgePills::make([
                    ['label' => __('Draft'), 'count' => $draftExams, 'dotClass' => 'bg-gray-300'],
                    ['label' => __('Published'), 'count' => $publishedExams, 'dotClass' => 'bg-emerald-300'],
                ]))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-sky-500 to-sky-600 [&_*]:!text-white']),

            Stat::make(__('Exam usage this month'), $monthlyLimit === null ? "{$usedThisMonth} (".__('Unlimited').')' : "{$usedThisMonth} / {$monthlyLimit}")
                ->icon('heroicon-o-calendar-days')
                ->color(match (true) {
                    $monthlyLimit === null => 'success',
                    $usedThisMonth >= $monthlyLimit => 'danger',
                    $usedThisMonth >= $monthlyLimit * 0.7 => 'warning',
                    default => 'success',
                })
                ->extraAttributes([
                    'class' => match (true) {
                        $monthlyLimit === null => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white',
                        $usedThisMonth >= $monthlyLimit => 'bg-gradient-to-br from-rose-500 to-rose-600 [&_*]:!text-white',
                        $usedThisMonth >= $monthlyLimit * 0.7 => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white',
                        default => 'bg-gradient-to-br from-emerald-500 to-emerald-600 [&_*]:!text-white',
                    },
                ]),

            Stat::make(__('Total attempts'), $totalAttempts)
                ->description(__('Across all exams'))
                ->icon('heroicon-o-users')
                ->color('warning')
                ->extraAttributes(['class' => 'bg-gradient-to-br from-amber-500 to-amber-600 [&_*]:!text-white']),
        ];
    }
}

<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\ExamType;
use App\Filament\Support\Pages\FilteredDashboard;
use App\Models\ClassSubject;
use App\Services\SubscriptionLimitService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * The teacher's dashboard, with which class + subject the class analytics
 * are of — all of the teacher's exams together by default.
 */
class Dashboard extends FilteredDashboard
{
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('class_subject_id')
                ->label(__('Class analytics of'))
                ->placeholder(__('All my exams'))
                ->options(fn (): array => ClassSubject::query()
                    ->whereHas('exams', fn ($exams) => $exams->where('created_by', Auth::id())->where('exam_type', ExamType::TeacherExam))
                    ->with(['academicClass', 'subject'])
                    ->get()
                    ->mapWithKeys(fn (ClassSubject $classSubject): array => [$classSubject->id => $classSubject->label()])
                    ->sort()
                    ->all())
                ->visible(fn (): bool => app(SubscriptionLimitService::class)->allowsAnalytics(Auth::user())),
        ]);
    }
}

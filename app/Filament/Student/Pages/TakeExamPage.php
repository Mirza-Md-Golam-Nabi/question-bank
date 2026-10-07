<?php

namespace App\Filament\Student\Pages;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ExamAttempt;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class TakeExamPage extends Page
{
    use TranslatesPageLabels;

    protected static ?string $slug = 'take-exam/{attempt}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.student.pages.take-exam-page';

    public ExamAttempt $attempt;

    /**
     * @var array<int, string>
     */
    public array $answers = [];

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->student_id === Auth::id(), 403);
        abort_if($attempt->status !== ExamAttemptStatus::InProgress, 403, __('This attempt has already been submitted.'));

        $this->attempt = $attempt->load('exam.questions');
    }

    public function submit(): void
    {
        foreach ($this->answers as $questionId => $studentAnswer) {
            $this->attempt->answers()->updateOrCreate(
                ['question_id' => $questionId],
                ['student_answer' => $studentAnswer],
            );
        }

        $this->attempt->submitAndAutoGrade();

        Notification::make()->title(__('Exam submitted'))->success()->send();

        $this->redirect(ExamResultPage::getUrl(['attempt' => $this->attempt->id]));
    }

    public function isMcq(int $questionId): bool
    {
        return $this->attempt->exam->questions->firstWhere('id', $questionId)?->question_type === QuestionType::Mcq;
    }
}

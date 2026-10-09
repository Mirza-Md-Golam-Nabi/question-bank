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

    /**
     * Where a student is sent for an attempt ExamAttempt::startFor() gave
     * them: into the exam while it is under way, and to its result once
     * it has been handed in — an exam is sat only once.
     */
    public static function urlFor(ExamAttempt $attempt): string
    {
        return $attempt->isInProgress()
            ? self::getUrl(['attempt' => $attempt->id], panel: 'student')
            : ExamResultPage::getUrl(['attempt' => $attempt->id, ExamResultPage::ALREADY_TAKEN => 1], panel: 'student');
    }

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->student_id === Auth::id(), 403);
        abort_if($attempt->status !== ExamAttemptStatus::InProgress, 403, __('This attempt has already been submitted.'));

        $this->attempt = $attempt->load('exam.subject', 'exam.classSubject.academicClass', 'student');

        // Anything already on record (e.g. saved when the clock ran out,
        // before a reload) is shown again rather than appearing blank.
        $this->answers = $this->attempt->answers()->pluck('student_answer', 'question_id')->all();
    }

    /**
     * Called by the page itself the moment the clock runs out, to put the
     * answers on record before it locks — so a late submit has nothing
     * left to change (see ExamAttempt::recordAnswers()).
     */
    public function saveAnswers(): void
    {
        $this->attempt->recordAnswers($this->answers);
    }

    public function submit(): void
    {
        $this->attempt->recordAnswers($this->answers);

        $this->attempt->submitAndAutoGrade();

        Notification::make()->title(__('Exam submitted'))->success()->send();

        $this->redirect(ExamResultPage::getUrl(['attempt' => $this->attempt->id]));
    }

    public function isMcq(int $questionId): bool
    {
        return $this->attempt->exam->questions->firstWhere('id', $questionId)?->question_type === QuestionType::Mcq;
    }
}

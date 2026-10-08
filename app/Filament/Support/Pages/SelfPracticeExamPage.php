<?php

namespace App\Filament\Support\Pages;

use App\Enums\ExamType;
use App\Filament\Student\Pages\TakeExamPage;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * What the Student panel's two self-practice pages have in common
 * (CLAUDE.md rule 9 — Auto-Generate and Manual Selection): the form
 * plumbing, the subject picker, and the one path every practice exam is
 * started through — monthly-limit check first, then straight into the
 * attempt. A page only defines its own form fields and how the exam is put
 * together.
 */
abstract class SelfPracticeExamPage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.student.pages.self-practice-exam';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    /**
     * The label of the page's submit button.
     */
    abstract public function submitLabel(): string;

    /**
     * Builds the exam from the validated form data and returns the attempt
     * started on it.
     *
     * @param  array<string, mixed>  $data
     */
    abstract protected function createAttempt(User $student, array $data): ExamAttempt;

    public function start(): void
    {
        $student = Auth::user();

        // Both modes count towards the same monthly limit (CLAUDE.md rule 9).
        if (app(SubscriptionLimitService::class)->hasReachedMonthlyLimit($student, ExamType::SelfPractice)) {
            Notification::make()
                ->title(__('Monthly free limit reached'))
                ->body(__('Upgrade your subscription to generate more practice exams this month.'))
                ->danger()
                ->send();

            return;
        }

        $attempt = $this->createAttempt($student, $this->form->getState());

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }

    /**
     * The subject picker both modes start with.
     *
     * @param  (Closure(callable): mixed)|null  $afterStateUpdated  Extra reset to run when the subject changes.
     */
    protected function subjectSelect(?Closure $afterStateUpdated = null): Select
    {
        return Select::make('subject_id')
            ->label(__('Subject'))
            ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->live()
            ->required()
            ->afterStateUpdated($afterStateUpdated);
    }
}

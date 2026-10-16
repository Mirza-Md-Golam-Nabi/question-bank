<?php

namespace App\Filament\Support\Pages;

use App\Enums\Difficulty;
use App\Filament\Student\Pages\MyProfile;
use App\Filament\Student\Pages\TakeExamPage;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * What the Student panel's self-practice pages have in common (CLAUDE.md
 * rule 9 — Auto-Generate, Manual Selection, and practising mistakes): the
 * form plumbing, the subject picker of the student's own class, and the one
 * path every practice exam is started through. Whether the student's plans
 * allow it is SelfPracticeExamService's question (through
 * SubscriptionLimitService); this page only shows the answer. A page
 * defines its own form fields and how the exam is put together.
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
     *
     * @throws ValidationException
     */
    abstract protected function createAttempt(User $student, ClassSubject $classSubject, array $data): ExamAttempt;

    /**
     * Practice is always in the student's own class, set on their profile.
     */
    public function hasClass(): bool
    {
        return Auth::user()->academic_class_id !== null;
    }

    public function profileUrl(): string
    {
        return MyProfile::getUrl();
    }

    public function start(): void
    {
        $data = $this->form->getState();
        $classSubject = $this->classSubject($data['class_subject_id'] ?? null);

        if (! $classSubject) {
            $this->addError('data.class_subject_id', __('Choose a subject.'));

            return;
        }

        try {
            $attempt = $this->createAttempt(Auth::user(), $classSubject, $data);
        } catch (ValidationException $exception) {
            // What the plan doesn't allow isn't a mistake in the form.
            if (! array_key_exists('plan', $exception->errors())) {
                throw $exception;
            }

            Notification::make()
                ->title(__('Your plan does not allow this'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }

    /**
     * One subject of the student's own class — never another class's, even
     * if its id is sent by hand.
     */
    public function classSubject(mixed $classSubjectId): ?ClassSubject
    {
        if (blank($classSubjectId) || ! $this->hasClass()) {
            return null;
        }

        return ClassSubject::query()
            ->where('academic_class_id', Auth::user()->academic_class_id)
            ->find($classSubjectId);
    }

    /**
     * The subjects of the student's class, by name.
     *
     * @return array<int, string>
     */
    protected function subjectOptions(): array
    {
        return once(fn (): array => ClassSubject::subjectOptionsFor(Auth::user()->academic_class_id));
    }

    /**
     * The difficulties the student's plans allow in a subject — every one
     * when nothing limits them.
     *
     * @return array<int, Difficulty>
     */
    protected function allowedDifficulties(mixed $classSubjectId): array
    {
        $classSubject = $this->classSubject($classSubjectId);

        if (! $classSubject) {
            return Difficulty::cases();
        }

        return app(SubscriptionLimitService::class)->allowedDifficultiesFor(Auth::user(), $classSubject) ?? Difficulty::cases();
    }

    /**
     * The subject picker every mode starts with.
     *
     * @param  (Closure(callable): mixed)|null  $afterStateUpdated  Extra reset to run when the subject changes.
     */
    protected function subjectSelect(?Closure $afterStateUpdated = null): Select
    {
        return Select::make('class_subject_id')
            ->label(__('Subject'))
            ->options(fn (): array => $this->subjectOptions())
            ->searchable()
            ->live()
            ->required()
            ->afterStateUpdated($afterStateUpdated);
    }
}

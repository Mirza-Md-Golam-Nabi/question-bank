<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\QuestionPickerPage;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Services\SelfPracticeExamService;
use App\Services\SubscriptionLimitService;
use Filament\Forms\Components\Hidden;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Self-practice, Manual Selection mode (CLAUDE.md rule 9), on the same
 * question picker the Teacher builds exams with: the student browses their
 * own class's subjects chapter by chapter, ticks approved MCQs up to the
 * number they want, and starts the exam from the final view. A practice
 * exam is always online — no printing, no CQ — and the cards don't show
 * the answers of questions the student is about to sit.
 */
class BuildPracticeExam extends QuestionPickerPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Practice;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Build Your Own Practice Exam';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Practice is always in the student's own class.
                Hidden::make('academic_class_id')->default(fn (): ?int => Auth::user()->academic_class_id),

                ...$this->hierarchyFilters(),
            ])
            ->statePath('data');
    }

    public function offersDeliveryModes(): bool
    {
        return false;
    }

    public function showsClassFilter(): bool
    {
        return false;
    }

    public function showsAnswersWhilePicking(): bool
    {
        return false;
    }

    protected function selectionStoragePrefix(): string
    {
        return 'qb:student-selection:';
    }

    protected function classSubjectQuery(): Builder
    {
        return ClassSubject::query()->where('academic_class_id', Auth::user()->academic_class_id);
    }

    /**
     * The subjects of the student's own class their plans reach.
     */
    protected function accessibleClassSubjects(): Builder
    {
        return app(SubscriptionLimitService::class)->constrainToAccessible($this->classSubjectQuery(), Auth::user());
    }

    protected function pickableQuestions(): Builder
    {
        return Question::approvedPool();
    }

    public function selectableQuestions(ClassSubject $classSubject, array $questionIds): Collection
    {
        return app(SelfPracticeExamService::class)->selectableQuestions($classSubject, $questionIds);
    }

    public function blockedNotice(): ?array
    {
        if (Auth::user()->academic_class_id === null) {
            return [
                'heading' => __('Set your class first'),
                'message' => __('Practice exams are made from your own class. Choose your class on your profile to start.'),
                'url' => MyProfile::getUrl(),
                'button' => __('Go to my profile'),
            ];
        }

        return $this->hasAnyPlan() ? null : [
            'heading' => __('You need a plan to practise'),
            'message' => __('Your plan does not cover any subject of your class yet. Buy a plan to build practice exams.'),
            'url' => MySubscription::getUrl(),
            'button' => __('See plans'),
        ];
    }

    public function reviewIntro(): string
    {
        return __('This is the final view of everything you selected, chapter by chapter. Remove anything you do not want, then start the exam.');
    }

    public function finishLabel(): string
    {
        return __('Start exam');
    }

    /**
     * Starts the practice exam on the selected questions — counted against
     * the student's plan like any other practice exam.
     */
    public function finish(array $questionIds): void
    {
        if (! $this->classSubject) {
            return;
        }

        try {
            $attempt = app(SelfPracticeExamService::class)->generateManual(Auth::user(), $this->classSubject, $questionIds);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title(__('The exam could not be started'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            // Re-run the review so both sides agree on what's left.
            $this->review($questionIds);

            return;
        }

        $this->dispatch('qb-selection-saved');

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }
}

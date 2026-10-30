<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\PaperSourceType;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Resources\BoardQuestionPapers\Pages\EditBoardQuestionPaper;
use App\Filament\Support\QuestionBlocks;
use App\Filament\Support\RelationManagers\PaperMcqQuestionsRelationManager;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion as TeacherEditQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\BoardMcqQuestion;
use App\Models\BoardMcqStimulus;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\StaffEarning;
use App\Models\User;
use App\Services\PastPaperExamService;
use App\Services\SelfPracticeExamService;
use App\Services\StimulusGroupService;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();
    $this->groups = app(StimulusGroupService::class);

    /** The group form's state: a stimulus and its MCQs. */
    $this->form = fn (array $questions = ['প্রথম?', 'দ্বিতীয়?'], array $overrides = []): array => [
        // The editor first: choosing it clears the text, as on the form.
        'editor_mode' => 'ckeditor',
        'question_text' => '<p>একটি বর্তনী।</p>',
        'question_image' => null,
        'topic_id' => null,
        'questions' => collect($questions)->map(fn (string|array $question) => is_array($question) ? $question : [
            'question_text' => "<p>{$question}</p>",
            'options' => [
                ['option' => 'ক', 'is_correct' => true],
                ['option' => 'খ', 'is_correct' => false],
            ],
            'marks' => 1,
            'difficulty' => 'easy',
        ])->all(),
        ...$overrides,
    ];

    /** A stimulus group of the question bank, saved, as `$author`. */
    $this->group = function (?User $author = null, array $questions = ['প্রথম?', 'দ্বিতীয়?']): QuestionStimulus {
        $this->actingAs($author ?? $this->teacher);

        return $this->groups->create($this->chapter, ($this->form)($questions));
    };
});

describe('laying questions out', function () {
    it('names the numbers a stimulus is for', function () {
        expect(QuestionBlocks::heading([5]))->toBe('Read the stimulus below and answer question 5.')
            ->and(QuestionBlocks::heading([5, 6]))->toBe('Read the stimulus below and answer questions 5 and 6.')
            ->and(QuestionBlocks::heading([5, 6, 7]))->toBe('Read the stimulus below and answer questions 5–7.');
    });

    it('keeps a stimulus\'s questions together, in their order, where the first of them stands', function () {
        $stimulus = QuestionStimulus::factory()->create();
        $second = Question::factory()->create(['stimulus_id' => $stimulus->id, 'stimulus_order' => 2]);
        $alone = Question::factory()->create();
        $first = Question::factory()->create(['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
        $last = Question::factory()->create();

        $blocks = QuestionBlocks::blocks([$second, $alone, $first, $last]);

        expect(QuestionBlocks::arrange([$second, $alone, $first, $last])->pluck('id')->all())->toBe([$first->id, $second->id, $alone->id, $last->id])
            ->and($blocks)->toHaveCount(3)
            ->and($blocks[0]['heading'])->toBe('Read the stimulus below and answer questions 1 and 2.')
            ->and($blocks[1]['stimulus'])->toBeNull()
            ->and($blocks[2]['questions'][0]['number'])->toBe(4);
    });
});

describe('a stimulus group in the question bank', function () {
    it('saves the stimulus and its MCQs as the author\'s own pending questions, in order', function () {
        $stimulus = ($this->group)();
        $questions = $stimulus->questions;

        expect($questions)->toHaveCount(2)
            ->and($questions->pluck('stimulus_order')->all())->toBe([1, 2])
            ->and($questions->every(fn (Question $question) => $question->status === QuestionStatus::Pending && $question->created_by === $this->teacher->id && $question->question_type === QuestionType::Mcq))->toBeTrue();
    });

    it('is approved and rejected as one, a staff member paid for each question once', function () {
        $staff = User::factory()->staff()->create();
        $stimulus = ($this->group)($staff, ['এক?', 'দুই?', 'তিন?']);

        $stimulus->questions->first()->approve($this->admin);

        expect($stimulus->questions()->where('status', QuestionStatus::Approved)->count())->toBe(3)
            ->and($staff->refresh()->staffProfile->total_questions_approved)->toBe(3);

        $stimulus->questions->last()->fresh()->reject($this->admin, 'Wrong figure');
        expect($stimulus->questions()->where('status', QuestionStatus::Rejected)->count())->toBe(3);

        $stimulus->questions->first()->fresh()->approve($this->admin);
        expect(StaffEarning::where('staff_id', $staff->id)->count())->toBe(6);
    });

    it('makes a new version of the whole group when an approved group is edited', function () {
        $stimulus = ($this->group)($this->admin);
        $old = $stimulus->questions;
        expect($old->every(fn (Question $question) => $question->status === QuestionStatus::Approved))->toBeTrue();

        $form = ($this->form)(['প্রথম (সংশোধিত)?']);
        $form['questions'][0]['id'] = $old[0]->id;
        $form['question_text'] = '<p>নতুন উদ্দীপক।</p>';

        $revised = $this->groups->update($stimulus, $form);

        expect($revised->id)->not->toBe($stimulus->id)
            ->and($stimulus->fresh()->text)->toBe('<p>একটি বর্তনী।</p>')
            ->and($revised->questions()->where('is_latest', true)->count())->toBe(1)
            ->and($revised->questions->first()->parent_id)->toBe($old[0]->id)
            ->and($revised->questions->first()->status)->toBe(QuestionStatus::Pending)
            // Both old questions leave the pool — the second was left out of the edit.
            ->and(Question::whereKey($old->pluck('id'))->where('is_latest', true)->count())->toBe(0);
    });

    it('corrects a group not approved yet in place, and keeps a question that is on an exam', function () {
        $stimulus = ($this->group)();
        [$first, $second] = $stimulus->questions->all();

        $form = ($this->form)(['প্রথম (ঠিক করা)?', 'নতুন তৃতীয়?']);
        $form['questions'][0]['id'] = $first->id;
        $this->groups->update($stimulus, $form);

        expect($stimulus->fresh()->questions()->pluck('question_text')->all())->toBe(['<p>প্রথম (ঠিক করা)?</p>', '<p>নতুন তৃতীয়?</p>'])
            ->and(Question::find($second->id))->toBeNull();

        // A question on an exam can't be taken out of its group.
        subscribeToEverything($this->teacher);
        $third = $stimulus->fresh()->questions->last();
        app(TeacherExamBuilder::class)->build($this->teacher, $this->chapter->classSubject, ExamDeliveryMode::Offline, [$third->id], 'Test', 30);

        $form = ($this->form)(['শুধু প্রথম?']);
        $form['questions'][0]['id'] = $first->id;

        expect(fn () => $this->groups->update($stimulus, $form))->toThrow(ValidationException::class);
    });

    it('is created and edited from a chapter\'s question list, and a group\'s question has no edit page of its own', function () {
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->callAction('createStimulusGroup', data: ($this->form)())
            ->assertHasNoFormErrors();

        $question = Question::firstOrFail();

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->assertActionHidden(TestAction::make('edit')->table($question))
            ->assertActionVisible(TestAction::make('editStimulusGroup')->table($question));

        livewire(TeacherEditQuestion::class, ['record' => $question->id])->assertRedirect();
    });

    it('may be only its image, but not empty', function () {
        Storage::fake('public');
        $this->actingAs($this->teacher);
        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->callAction('createStimulusGroup', data: ($this->form)(overrides: ['question_text' => null]))
            ->assertHasFormErrors(['question_text' => 'required']);

        livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
            ->callAction('createStimulusGroup', data: ($this->form)(overrides: ['question_text' => null, 'question_image' => UploadedFile::fake()->image('s44.png')]))
            ->assertHasNoFormErrors();

        expect(QuestionStimulus::sole())->text->toBe('')->image->not->toBeNull();
    });
});

describe('on an exam', function () {
    it('heads a group with the numbers it lands on, whichever of its questions were picked', function () {
        subscribeToEverything($this->teacher);
        $stimulus = ($this->group)($this->admin, ['এক?', 'দুই?', 'তিন?']);
        $others = Question::factory()->approved()->count(3)->create(['chapter_id' => $this->chapter->id]);
        $picked = [$stimulus->questions[0]->id, $stimulus->questions[2]->id, ...$others->pluck('id')];

        $exam = app(TeacherExamBuilder::class)->build($this->teacher, $this->chapter->classSubject, ExamDeliveryMode::Online, $picked, 'Test', 30);
        $exam->publish();

        foreach (range(1, 4) as $guest) {
            $attempt = ExamAttempt::startForGuest($exam->refresh(), "Guest {$guest}", "0171111111{$guest}");
            $html = view('components.exam-paper', ['attempt' => $attempt, 'slot' => ''])->render();

            preg_match('/answer questions (\d+) and (\d+)\./', $html, $numbers);

            // The two picked questions stand together under the stimulus,
            // shown once, wherever the shuffle put them.
            expect($numbers)->not->toBe([])
                ->and((int) $numbers[2])->toBe((int) $numbers[1] + 1)
                ->and(substr_count($html, 'একটি বর্তনী।'))->toBe(1);
        }
    });

    it('brings a question\'s stimulus into self-practice, even alone', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);
        $stimulus = ($this->group)($this->admin);
        $question = $stimulus->questions->first();

        $attempt = app(SelfPracticeExamService::class)->generateManual($student, $this->chapter->classSubject, [$question->id]);
        $html = view('components.exam-paper', ['attempt' => $attempt, 'slot' => ''])->render();

        expect($html)->toContain('answer question 1.')->toContain('একটি বর্তনী।');
    });

    it('prints a group\'s stimulus once', function () {
        subscribeToEverything($this->teacher);
        $stimulus = ($this->group)($this->admin);
        $exam = app(TeacherExamBuilder::class)->build($this->teacher, $this->chapter->classSubject, ExamDeliveryMode::Offline, $stimulus->questions->pluck('id')->all(), 'Test', 30);

        $html = $this->actingAs($this->teacher)->get(route('filament.teacher.exams.print', $exam))->assertOk()->getContent();

        expect(substr_count($html, 'একটি বর্তনী।'))->toBe(1)
            ->and($html)->toContain('answer questions 1 and 2.');
    });
});

describe('a stimulus group on a past paper', function () {
    beforeEach(function () {
        $this->paper = BoardQuestionPaper::factory()->admission(PaperSourceType::Medical)->create();
        $this->actingAs($this->admin);
    });

    it('is added and edited from the paper\'s MCQ list', function () {
        $list = fn () => livewire(PaperMcqQuestionsRelationManager::class, ['ownerRecord' => $this->paper, 'pageClass' => EditBoardQuestionPaper::class]);
        $form = ($this->form)();
        $form['questions'][1]['explanation'] = '<p>কারণ।</p>';

        $list()->callAction(TestAction::make('createStimulusGroup')->table(), $form)->assertHasNoFormErrors();

        $stimulus = BoardMcqStimulus::sole();
        $questions = $stimulus->questions;

        expect($questions)->toHaveCount(2)
            ->and($questions[1]->explanation)->toBe('<p>কারণ।</p>');

        $edit = ($this->form)(['শুধু একটা?', 'আরেকটা?']);
        $edit['questions'][0]['id'] = $questions[0]->id;

        $list()->callAction(TestAction::make('editStimulusGroup')->table($questions[0]), $edit)->assertHasNoFormErrors();

        expect($stimulus->questions()->pluck('question_text')->all())->toBe(['<p>শুধু একটা?</p>', '<p>আরেকটা?</p>'])
            ->and(BoardMcqQuestion::find($questions[1]->id))->toBeNull();
    });

    it('stands under its stimulus when the paper is sat', function () {
        $stimulus = BoardMcqStimulus::factory()->create(['board_question_paper_id' => $this->paper->id, 'text' => '<p>উদ্দীপক</p>']);
        BoardMcqQuestion::factory()->create(['board_question_paper_id' => $this->paper->id, 'order_index' => 1]);
        BoardMcqQuestion::factory()->count(2)->sequence(['stimulus_order' => 1], ['stimulus_order' => 2])->create([
            'board_question_paper_id' => $this->paper->id, 'stimulus_id' => $stimulus->id, 'order_index' => 2,
        ]);
        $this->paper->update(['status' => QuestionStatus::Approved]);
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        $attempt = app(PastPaperExamService::class)->startPractice($student, $this->paper->refresh());
        $html = view('components.exam-paper', ['attempt' => $attempt, 'slot' => ''])->render();

        expect(substr_count($html, 'উদ্দীপক'))->toBe(1)
            ->and($html)->toMatch('/answer questions (\d+) and (\d+)\./');
    });
});

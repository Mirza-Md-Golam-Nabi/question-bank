<?php

use App\Enums\EditorMode;
use App\Enums\ExamDeliveryMode;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\StimulusGroupService;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->teacher = User::factory()->teacher()->create();
    $this->chapter = Chapter::factory()->create();

    $this->explained = fn (array $attributes = []): Question => Question::factory()->approved()->create([
        'chapter_id' => $this->chapter->id,
        'explanation' => '<p>Because two and two make four.</p>',
        ...$attributes,
    ]);
});

describe('writing it', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $classSubject = ClassSubject::find($this->chapter->class_subject_id);

        $this->mcqForm = fn (array $overrides = []): array => [
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'ckeditor',
            'difficulty' => 'easy',
            'question_text' => '<p>2 + 2 = ?</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
            'explanation' => '<p>Two and two make four.</p>',
            ...$overrides,
        ];
    });

    it('saves an MCQ\'s explanation from the question form, and leaves it optional', function () {
        livewire(CreateQuestion::class)->fillForm(($this->mcqForm)())->call('create')->assertHasNoFormErrors();
        livewire(CreateQuestion::class)->fillForm(($this->mcqForm)(['question_text' => '<p>3 + 3 = ?</p>', 'explanation' => null]))->call('create')->assertHasNoFormErrors();

        expect(Question::orderBy('id')->pluck('explanation')->all())->toBe(['<p>Two and two make four.</p>', null]);
    });

    it('writes the explanation in Rich Text to start with, or in CKEditor when picked, and remembers which', function () {
        livewire(CreateQuestion::class)
            ->assertFormSet(['explanation_editor_mode' => EditorMode::RichText])
            ->fillForm(($this->mcqForm)(['explanation' => '<table><tbody><tr><td>ক</td></tr></tbody></table>']))
            ->call('create')
            ->assertHasNoFormErrors();

        livewire(CreateQuestion::class)
            ->fillForm(($this->mcqForm)(['question_text' => '<p>3 + 3 = ?</p>']))
            ->fillForm(['explanation_editor_mode' => 'ckeditor'])
            ->fillForm(['explanation' => '<p>Three and three make six.</p>'])
            ->call('create')
            ->assertHasNoFormErrors();

        [$richText, $ckEditor] = Question::orderBy('id')->get()->all();

        expect($richText->explanation_editor_mode)->toBe(EditorMode::RichText)
            ->and($richText->explanation)->toContain('<table>')
            ->and($ckEditor->explanation_editor_mode)->toBe(EditorMode::CkEditor)
            ->and($ckEditor->explanation)->toBe('<p>Three and three make six.</p>');

        livewire(EditQuestion::class, ['record' => $ckEditor->id])
            ->assertFormSet(['explanation_editor_mode' => EditorMode::CkEditor, 'explanation' => '<p>Three and three make six.</p>']);
    });

    it('starts the explanation over when its editor is switched', function () {
        livewire(CreateQuestion::class)
            ->fillForm(['explanation' => '<p>Written in Rich Text.</p>'])
            ->fillForm(['explanation_editor_mode' => 'ckeditor'])
            ->assertFormSet(['explanation' => null]);
    });

    it('carries the explanation into the new version when an approved question is edited', function () {
        $question = ($this->explained)();

        livewire(EditQuestion::class, ['record' => $question->id])
            ->assertFormSet(['explanation' => '<p>Because two and two make four.</p>'])
            ->fillForm(['explanation' => '<p>Corrected.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $revision = Question::where('parent_id', $question->id)->sole();

        expect($revision->explanation)->toBe('<p>Corrected.</p>')
            ->and($question->fresh()->explanation)->toBe('<p>Because two and two make four.</p>');
    });

    it('drops the explanation of a question turned into a CQ', function () {
        $question = ($this->explained)(['status' => 'pending']);

        livewire(EditQuestion::class, ['record' => $question->id])
            ->fillForm([
                'question_type' => 'cq',
                'cq_parts' => [
                    'knowledge' => ['part_text' => '<p>ক</p>', 'marks' => 1],
                    'comprehension' => ['part_text' => '<p>খ</p>', 'marks' => 2],
                    'application' => ['part_text' => '<p>গ</p>', 'marks' => 3],
                    'higher_application' => ['part_text' => '<p>ঘ</p>', 'marks' => 4],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($question->fresh()->explanation)->toBeNull();
    });

    it('saves each question\'s explanation in a stimulus group, and carries it into the group\'s new version', function () {
        $question = fn (string $text, ?string $explanation): array => [
            'question_text' => "<p>{$text}</p>",
            'options' => [['option' => 'ক', 'is_correct' => true], ['option' => 'খ', 'is_correct' => false]],
            'marks' => 1,
            'difficulty' => 'easy',
            'explanation' => $explanation,
        ];
        $form = fn (array $questions): array => [
            'editor_mode' => 'ckeditor',
            'question_text' => '<p>একটি বর্তনী।</p>',
            'question_image' => null,
            'topic_id' => null,
            'questions' => $questions,
        ];
        $groups = app(StimulusGroupService::class);

        $stimulus = $groups->create($this->chapter, $form([$question('প্রথম?', '<p>প্রথমটির ব্যাখ্যা।</p>'), $question('দ্বিতীয়?', null)]));

        expect($stimulus->questions->pluck('explanation')->all())->toBe(['<p>প্রথমটির ব্যাখ্যা।</p>', null]);

        $edited = $question('প্রথম?', '<p>সংশোধিত ব্যাখ্যা।</p>');
        $edited['id'] = $stimulus->questions->first()->id;
        $revised = $groups->update($stimulus, $form([$edited]));

        expect($revised->questions->first()->explanation)->toBe('<p>সংশোধিত ব্যাখ্যা।</p>')
            ->and($stimulus->questions->first()->fresh()->explanation)->toBe('<p>প্রথমটির ব্যাখ্যা।</p>');
    });
});

it('shows the explanation, and a group question\'s stimulus, in the "View question" window', function () {
    $this->actingAs($this->admin);
    $stimulus = QuestionStimulus::factory()->create(['chapter_id' => $this->chapter->id, 'text' => '<p>একটি বর্তনী।</p>']);
    $explained = ($this->explained)(['stimulus_id' => $stimulus->id, 'stimulus_order' => 1]);
    $plain = ($this->explained)(['explanation' => null, 'question_text' => '<p>Another question?</p>']);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->mountAction(TestAction::make('view')->table($explained))
        ->assertMountedActionModalSee(['Because two and two make four.', 'একটি বর্তনী।']);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->mountAction(TestAction::make('view')->table($plain))
        ->assertMountedActionModalSee('No explanation')
        ->assertMountedActionModalDontSee('একটি বর্তনী।');
});

describe('showing it only beside the correct answer', function () {
    it('never shows it while the exam is being sat', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        $attempt = app(SelfPracticeExamService::class)->generateManual($student, $this->chapter->classSubject, [($this->explained)()->id]);

        expect(view('components.exam-paper', ['attempt' => $attempt, 'slot' => ''])->render())
            ->not->toContain('Because two and two make four.');
    });

    it('shows it on a self-practice result straight away', function () {
        $student = User::factory()->student()->create();
        subscribeToEverything($student);

        $attempt = app(SelfPracticeExamService::class)->generateManual($student, $this->chapter->classSubject, [($this->explained)()->id]);
        $attempt->submitAndAutoGrade();

        expect(view('components.exam-result', ['attempt' => $attempt->refresh()->load('answers'), 'slot' => ''])->render())
            ->toContain('Because two and two make four.');
    });

    it('shows it on a teacher\'s exam result only once the answers are released', function () {
        subscribeToEverything($this->teacher);
        $exam = app(TeacherExamBuilder::class)->build($this->teacher, $this->chapter->classSubject, ExamDeliveryMode::Online, [($this->explained)()->id], 'Test', 30);
        $exam->publish();

        $attempt = ExamAttempt::startForGuest($exam->refresh(), 'Guest', '01711111111');
        $attempt->submitAndAutoGrade();
        $result = fn (): string => view('components.exam-result', ['attempt' => $attempt->refresh()->load('answers', 'exam'), 'slot' => ''])->render();

        expect($result())->not->toContain('Because two and two make four.');

        $exam->releaseAnswers();

        expect($result())->toContain('Because two and two make four.');
    });

    it('prints it on the copy with answers, not on the question paper', function () {
        subscribeToEverything($this->teacher);
        $exam = app(TeacherExamBuilder::class)->build($this->teacher, $this->chapter->classSubject, ExamDeliveryMode::Offline, [($this->explained)()->id], 'Test', 30);
        $this->actingAs($this->teacher);

        $this->get(route('filament.teacher.exams.print', $exam))
            ->assertOk()
            ->assertDontSee('Because two and two make four.');

        $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 1]))
            ->assertOk()
            ->assertSee('Because two and two make four.');
    });
});

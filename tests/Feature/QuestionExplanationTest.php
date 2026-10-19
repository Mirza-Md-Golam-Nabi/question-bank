<?php

use App\Enums\ExamDeliveryMode;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\StimulusGroupService;
use App\Services\TeacherExamBuilder;
use Database\Seeders\RoleSeeder;

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

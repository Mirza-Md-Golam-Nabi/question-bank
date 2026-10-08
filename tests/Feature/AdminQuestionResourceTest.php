<?php

use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->chapter = Chapter::factory()->create();
});

it('pre-selects class, subject, and chapter when arriving from a chapter\'s "add question" link', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    Livewire::withQueryParams(['chapter' => $this->chapter->id]);

    livewire(CreateQuestion::class)
        ->assertFormSet([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
        ]);
});

it('still applies every field\'s default value when arriving from a chapter\'s "add question" link', function () {
    Livewire::withQueryParams(['chapter' => $this->chapter->id]);

    livewire(CreateQuestion::class)
        ->assertFormSet([
            'question_type' => QuestionType::Mcq,
            'difficulty' => Difficulty::Easy,
            'marks' => 1,
        ]);
});

it('shows 4 default mcq option rows on the create page', function () {
    $test = livewire(CreateQuestion::class);
    $test->html();

    expect($test->instance()->data['options'] ?? [])->toHaveCount(4);
});

it('shows the question preview panel in both editor modes', function () {
    livewire(CreateQuestion::class)
        ->assertSee('qb-question-preview', escape: false)
        ->set('data.editor_mode', 'ckeditor')
        ->assertSee('qb-question-preview', escape: false);
});

it('renders the typed question text inside the preview panel for CKEditor', function () {
    livewire(CreateQuestion::class)
        ->set('data.editor_mode', 'ckeditor')
        ->set('data.question_text', '<p>একটি পরীক্ষার প্রশ্ন <span class="qb-katex-embed">x^2</span></p>')
        ->assertSee('একটি পরীক্ষার প্রশ্ন', escape: false)
        ->assertSee('qb-katex-embed', escape: false);
});

it('renders the typed question text inside the preview panel for Rich Text, converting its Tiptap JSON to HTML', function () {
    livewire(CreateQuestion::class)
        ->set('data.question_text', [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'একটি রিচ টেক্সট প্রশ্ন'],
                    ],
                ],
            ],
        ])
        ->assertSee('একটি রিচ টেক্সট প্রশ্ন', escape: false);
});

it('renders the uploaded diagram image inside the preview panel', function () {
    Storage::fake('public');

    livewire(CreateQuestion::class)
        ->set('data.editor_mode', 'ckeditor')
        ->set('data.question_text', '<p>Diagram question</p>')
        ->set('data.question_image', [UploadedFile::fake()->image('diagram.jpg')])
        ->assertSee('qb-question-preview-image', escape: false);
});

it('defaults question_text to the Rich Text editor', function () {
    livewire(CreateQuestion::class)
        ->assertFormSet(['editor_mode' => EditorMode::RichText]);
});

it('remembers the last editor a user saved a question with and defaults new questions to it', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'ckeditor',
            'difficulty' => 'easy',
            'question_text' => '<p>Question one</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    livewire(CreateQuestion::class)
        ->assertFormSet(['editor_mode' => EditorMode::CkEditor]);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'richtext',
            'difficulty' => 'easy',
            'question_text' => '<p>Question two</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    livewire(CreateQuestion::class)
        ->assertFormSet(['editor_mode' => EditorMode::RichText]);
});

it('does not leak one user\'s editor preference into another user\'s default', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'ckeditor',
            'difficulty' => 'easy',
            'question_text' => '<p>Question</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->actingAs(User::factory()->admin()->create());

    livewire(CreateQuestion::class)
        ->assertFormSet(['editor_mode' => EditorMode::RichText]);
});

it('clears question_text when the editor mode is switched, since the two editors store incompatible formats', function () {
    livewire(CreateQuestion::class)
        ->set('data.question_text', '<p>Some rich text content</p>')
        ->set('data.editor_mode', EditorMode::CkEditor->value)
        ->assertFormSet(['question_text' => null]);
});

it('creates a question authored with CKEditor and persists which editor wrote it', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'ckeditor',
            'difficulty' => 'easy',
            'question_text' => '<p>Pythagoras: <span class="qb-katex-embed">a^2 + b^2 = c^2</span></p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::first();

    expect($question->editor_mode)->toBe(EditorMode::CkEditor);
    expect($question->question_text)->toContain('qb-katex-embed');
});

it('pre-selects the CKEditor mode when editing a question authored with it', function () {
    $question = Question::factory()->approved()->for($this->chapter)->create([
        'editor_mode' => EditorMode::CkEditor,
        'question_text' => '<p>Pythagoras: <span class="qb-katex-embed">a^2 + b^2 = c^2</span></p>',
    ]);

    livewire(EditQuestion::class, ['record' => $question->id])
        ->assertFormSet([
            'editor_mode' => EditorMode::CkEditor,
            'question_text' => $question->question_text,
        ]);
});

it('creates an auto-approved mcq question as admin', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'difficulty' => 'easy',
            'question_text' => '<p>2+2=?</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::first();

    expect($question->status)->toBe(QuestionStatus::Approved);
    expect($question->approved_by)->toBe($this->admin->id);
    expect($question->options)->toHaveCount(2);
    expect(collect($question->options)->firstWhere('is_correct', true)['option'])->toBe('4');
});

it('creates a cq question with 4 parts and auto-sums the marks', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'cq',
            'difficulty' => 'medium',
            'question_text' => '<p>Stimulus text</p>',
            'cq_parts' => [
                'knowledge' => ['text' => '<p>K</p>', 'marks' => 1],
                'comprehension' => ['text' => '<p>C</p>', 'marks' => 2],
                'application' => ['text' => '<p>A</p>', 'marks' => 3],
                'higher_application' => ['text' => '<p>H</p>', 'marks' => 4],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $question = Question::first();

    expect($question->cqParts)->toHaveCount(4);
    expect((float) $question->marks)->toBe(10.0);
});

it('approves and rejects questions from the table with a logged reason', function () {
    // Created while acting as a teacher so the observer leaves them pending
    // — creating them while acting as admin would auto-approve them, which
    // would hide the approve/reject row actions before we ever get to test them.
    $teacher = User::factory()->teacher()->create();
    $this->actingAs($teacher);
    $question = Question::factory()->for($this->chapter)->create(['created_by' => $teacher->id]);
    $another = Question::factory()->for($this->chapter)->create(['created_by' => $teacher->id]);
    $this->actingAs($this->admin);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->callTableAction('approve', $question)
        ->assertHasNoTableActionErrors();

    expect($question->refresh()->status)->toBe(QuestionStatus::Approved);
    expect($question->approvalLogs()->count())->toBe(1);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->callTableAction('reject', $another, data: ['rejection_reason' => 'Not clear enough'])
        ->assertHasNoTableActionErrors();

    expect($another->refresh()->status)->toBe(QuestionStatus::Rejected);
    expect($another->rejection_reason)->toBe('Not clear enough');
});

it('turns an edit of an approved question into a new pending revision', function () {
    $question = Question::factory()->approved()->for($this->chapter)->create();
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    livewire(EditQuestion::class, ['record' => $question->id])
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'difficulty' => 'easy',
            'question_text' => '<p>Edited text</p>',
            'marks' => 1,
            'options' => $question->options,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $question->refresh();

    expect($question->is_latest)->toBeFalse();
    expect($question->status)->toBe(QuestionStatus::Approved);
    expect(Question::where('parent_id', $question->id)->where('status', 'pending')->exists())->toBeTrue();
});

// assertSee() can't reliably inspect this Filament version's action-modal
// content in tests — the modal renders as a separate Livewire "partial"
// (wire:partial="action-modals") that isn't included in the page HTML these
// helpers check, confirmed against a real browser render (which shows the
// full, correct content: class/subject/chapter, the "CKEditor" editor
// badge, properly KaTeX-rendered math, and both options with the correct
// one highlighted). So these tests guard against the schema crashing for
// each question_type shape — a real regression risk (e.g. a bad relation
// name or field), which assertHasNoActionErrors() does catch — rather than
// asserting exact rendered text.
it('opens the View action without error for an mcq question', function () {
    $question = Question::factory()->approved()->for($this->chapter)->create([
        'editor_mode' => EditorMode::CkEditor,
        'question_text' => '<p>Pythagoras: <span class="qb-katex-embed">a^2 + b^2 = c^2</span></p>',
        'options' => [
            ['option' => 'Three', 'image' => null, 'is_correct' => false],
            ['option' => 'Four', 'image' => null, 'is_correct' => true],
        ],
    ]);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction(TestAction::make('view')->table($question))
        ->assertHasNoActionErrors();
});

it('opens the View action without error for a cq question with all 4 parts', function () {
    $question = Question::factory()->cq()->approved()->for($this->chapter)->create([
        'question_text' => '<p>Stimulus text</p>',
    ]);
    $question->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>Knowledge part</p>', 'marks' => 1]);
    $question->cqParts()->create(['part_type' => 'comprehension', 'part_order' => 2, 'part_text' => '<p>Comprehension part</p>', 'marks' => 2]);
    $question->cqParts()->create(['part_type' => 'application', 'part_order' => 3, 'part_text' => '<p>Application part</p>', 'marks' => 3]);
    $question->cqParts()->create(['part_type' => 'higher_application', 'part_order' => 4, 'part_text' => '<p>Higher application part</p>', 'marks' => 4]);

    livewire(ListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction(TestAction::make('view')->table($question))
        ->assertHasNoActionErrors();
});

it('saves the selected topic of the chapter on a question, and allows leaving it empty', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations', 'order_index' => 1]);

    $formData = [
        'academic_class_id' => $classSubject->academic_class_id,
        'class_subject_id' => $classSubject->id,
        'chapter_id' => $this->chapter->id,
        'question_type' => 'mcq',
        'editor_mode' => 'richtext',
        'difficulty' => 'easy',
        'marks' => 1,
        'options' => [
            ['option' => '3', 'is_correct' => false],
            ['option' => '4', 'is_correct' => true],
        ],
    ];

    livewire(CreateQuestion::class)
        ->fillForm([...$formData, 'topic_id' => $topic->id, 'question_text' => '<p>With topic</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    livewire(CreateQuestion::class)
        ->fillForm([...$formData, 'topic_id' => null, 'question_text' => '<p>Without topic</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Question::where('topic_id', $topic->id)->count())->toBe(1);
    expect(Question::whereNull('topic_id')->count())->toBe(1);
});

it('rejects a topic that belongs to a different chapter', function () {
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);
    $otherChapterTopic = Topic::create(['chapter_id' => Chapter::factory()->create()->id, 'name' => 'Elsewhere', 'order_index' => 1]);

    livewire(CreateQuestion::class)
        ->fillForm([
            'academic_class_id' => $classSubject->academic_class_id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $this->chapter->id,
            'question_type' => 'mcq',
            'editor_mode' => 'richtext',
            'difficulty' => 'easy',
            'question_text' => '<p>Question</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ])
        ->set('data.topic_id', $otherChapterTopic->id)
        ->call('create')
        ->assertHasFormErrors(['topic_id']);

    expect(Question::count())->toBe(0);
});

it('clears the selected topic when the chapter is changed', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations', 'order_index' => 1]);

    livewire(CreateQuestion::class)
        ->fillForm(['chapter_id' => $this->chapter->id, 'topic_id' => $topic->id])
        ->set('data.chapter_id', Chapter::factory()->create()->id)
        ->assertFormSet(['topic_id' => null]);
});

describe('remembered topic', function () {
    beforeEach(function () {
        $this->classSubject = ClassSubject::find($this->chapter->class_subject_id);
        $this->topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations', 'order_index' => 1]);
        $this->questionFormData = [
            'academic_class_id' => $this->classSubject->academic_class_id,
            'class_subject_id' => $this->classSubject->id,
            'chapter_id' => $this->chapter->id,
            'topic_id' => $this->topic->id,
            'question_type' => 'mcq',
            'editor_mode' => 'richtext',
            'difficulty' => 'easy',
            'question_text' => '<p>Question</p>',
            'marks' => 1,
            'options' => [
                ['option' => '3', 'is_correct' => false],
                ['option' => '4', 'is_correct' => true],
            ],
        ];
    });

    it('keeps the class, subject, chapter, and topic selected after "create & create another"', function () {
        livewire(CreateQuestion::class)
            ->fillForm($this->questionFormData)
            ->call('create', true)
            ->assertHasNoFormErrors()
            ->assertFormSet([
                'academic_class_id' => $this->classSubject->academic_class_id,
                'class_subject_id' => $this->classSubject->id,
                'chapter_id' => $this->chapter->id,
                'topic_id' => $this->topic->id,
            ]);
    });

    it('pre-selects the last used topic the next time a question is added to that chapter', function () {
        livewire(CreateQuestion::class)->fillForm($this->questionFormData)->call('create')->assertHasNoFormErrors();

        Livewire::withQueryParams(['chapter' => $this->chapter->id]);

        livewire(CreateQuestion::class)->assertFormSet(['topic_id' => $this->topic->id]);
    });

    it('restores the last used topic when its chapter is picked by hand', function () {
        livewire(CreateQuestion::class)->fillForm($this->questionFormData)->call('create')->assertHasNoFormErrors();

        livewire(CreateQuestion::class)
            ->set('data.chapter_id', $this->chapter->id)
            ->assertFormSet(['topic_id' => $this->topic->id]);
    });

    it('does not carry a remembered topic over to another chapter or another user', function () {
        livewire(CreateQuestion::class)->fillForm($this->questionFormData)->call('create')->assertHasNoFormErrors();

        Livewire::withQueryParams(['chapter' => Chapter::factory()->create()->id]);
        livewire(CreateQuestion::class)->assertFormSet(['topic_id' => null]);

        $this->actingAs(User::factory()->admin()->create());
        Livewire::withQueryParams(['chapter' => $this->chapter->id]);
        livewire(CreateQuestion::class)->assertFormSet(['topic_id' => null]);
    });

    it('forgets the remembered topic once a question is saved without one, or the topic is deleted', function () {
        livewire(CreateQuestion::class)->fillForm($this->questionFormData)->call('create')->assertHasNoFormErrors();
        livewire(CreateQuestion::class)->fillForm([...$this->questionFormData, 'topic_id' => null])->call('create')->assertHasNoFormErrors();

        Livewire::withQueryParams(['chapter' => $this->chapter->id]);
        livewire(CreateQuestion::class)->assertFormSet(['topic_id' => null]);

        Livewire::withQueryParams([]);
        livewire(CreateQuestion::class)->fillForm($this->questionFormData)->call('create')->assertHasNoFormErrors();
        $this->topic->delete();

        Livewire::withQueryParams(['chapter' => $this->chapter->id]);
        livewire(CreateQuestion::class)->assertFormSet(['topic_id' => null]);
    });
});

<?php

use App\Enums\CqPartType;
use App\Enums\Difficulty;
use App\Enums\EditorMode;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Filament\Staff\Resources\Questions\Pages\ListQuestions as StaffListQuestions;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\BillingSetting;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use App\Services\QuestionImportText;
use App\Services\QuestionJsonImporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    $this->chapter = Chapter::factory()->create();
    $this->importer = new QuestionJsonImporter;

    $this->import = fn (array|string $questions, ?int $topicId = null): array => $this->importer->import(
        is_string($questions) ? $questions : json_encode($questions),
        $this->chapter,
        $topicId,
        Difficulty::Medium,
    );

    $this->problems = function (array|string $questions): array {
        try {
            ($this->import)($questions);
        } catch (ValidationException $exception) {
            return $exception->errors()[QuestionJsonImporter::ERROR_KEY];
        }

        return [];
    };

    $this->mcq = fn (array $overrides = []): array => [
        'question' => 'HTML এর পূর্ণরূপ কী?',
        'options' => ['Hyper Text Markup Language', 'High Text Machine Language', 'Hyper Tool Markup Language', 'Home Text Markup Language'],
        'answer' => 1,
        ...$overrides,
    ];

    $this->cq = fn (array $overrides = []): array => [
        'type' => 'cq',
        'question' => 'একটি উদ্দীপক।',
        'parts' => [
            ['text' => 'জ্ঞানমূলক?', 'marks' => 1],
            ['text' => 'অনুধাবনমূলক?', 'marks' => 2],
            ['text' => 'প্রয়োগমূলক?', 'marks' => 3],
            ['text' => 'উচ্চতর দক্ষতা?', 'marks' => 4],
        ],
        ...$overrides,
    ];
});

it('adds the MCQs of the JSON to the chapter as the teacher\'s own pending questions', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'HTML', 'order_index' => 1]);

    $result = ($this->import)([($this->mcq)(), ($this->mcq)(['question' => 'দ্বিতীয় প্রশ্ন?', 'answer' => 3, 'difficulty' => 'hard', 'marks' => 2])], $topic->id);

    expect($result)->toBe(['added' => 2, 'skipped' => 0]);

    [$first, $second] = Question::orderBy('id')->get()->all();

    expect($first->chapter_id)->toBe($this->chapter->id)
        ->and($first->topic_id)->toBe($topic->id)
        ->and($first->created_by)->toBe($this->teacher->id)
        ->and($first->status)->toBe(QuestionStatus::Pending)
        ->and($first->question_type)->toBe(QuestionType::Mcq)
        ->and($first->editor_mode)->toBe(EditorMode::CkEditor)
        ->and($first->question_text)->toBe('<p>HTML এর পূর্ণরূপ কী?</p>')
        ->and($first->difficulty)->toBe(Difficulty::Medium)
        ->and((float) $first->marks)->toBe(1.0)
        ->and(array_column($first->options, 'is_correct'))->toBe([true, false, false, false])
        ->and($first->options[0]['option'])->toBe('<p>Hyper Text Markup Language</p>');

    expect($second->difficulty)->toBe(Difficulty::Hard)
        ->and((float) $second->marks)->toBe(2.0)
        ->and(array_column($second->options, 'is_correct'))->toBe([false, false, true, false]);
});

it('approves an admin\'s imported questions straight away, like the ones typed into the form', function () {
    $this->actingAs(User::factory()->admin()->create());

    ($this->import)([($this->mcq)()]);

    expect(Question::sole()->status)->toBe(QuestionStatus::Approved);
});

it('adds a CQ with its four parts and sums their marks', function () {
    ($this->import)([($this->cq)()]);

    $question = Question::sole();

    expect($question->question_type)->toBe(QuestionType::Cq)
        ->and((float) $question->marks)->toBe(10.0)
        ->and($question->cqParts->pluck('part_type')->all())->toBe(CqPartType::ordered())
        ->and($question->cqParts->pluck('part_text')->all())->toBe(['<p>জ্ঞানমূলক?</p>', '<p>অনুধাবনমূলক?</p>', '<p>প্রয়োগমূলক?</p>', '<p>উচ্চতর দক্ষতা?</p>']);
});

it('stores a formula as a KaTeX embed and everything else as escaped text', function () {
    ($this->import)([($this->mcq)([
        'question' => '<b>মান</b> কত: $\frac{1}{2} + x^2$ ? দাম \$5',
        'options' => ['$1$', '<h1>', 'খ', 'গ'],
    ])]);

    $question = Question::sole();

    expect($question->question_text)->toBe('<p>&lt;b&gt;মান&lt;/b&gt; কত: <span class="qb-katex-embed">\frac{1}{2} + x^2</span> ? দাম $5</p>')
        ->and($question->options[0]['option'])->toBe('<p><span class="qb-katex-embed">1</span></p>')
        ->and($question->options[1]['option'])->toBe('<p>&lt;h1&gt;</p>');
});

it('accepts the sample it offers', function () {
    $result = ($this->import)(QuestionJsonImporter::sampleJson());

    expect($result['added'])->toBe(2);
});

it('offers a format guide to copy that ends with that same sample', function () {
    $guide = QuestionJsonImporter::formatGuide();

    expect($guide)->toEndWith(QuestionJsonImporter::sampleJson())
        ->and($guide)->toContain('\\\\frac')
        ->and($guide)->toContain('easy, medium, hard');

    // Shown in full, with its copy button, on the window's first step.
    $window = livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->mountAction('importQuestionsFromJson');

    view()->share('errors', new ViewErrorBag);
    $html = $window->instance()->getSchema('mountedActionSchema0')->toHtml();

    expect($html)->toContain(e($guide))
        ->and($html)->toContain('copy()');
});

it('accepts JSON wrapped in a code fence or in a "questions" key', function () {
    ($this->import)("```json\n".json_encode(['questions' => [($this->mcq)()]])."\n```");

    expect(Question::count())->toBe(1);
});

it('saves nothing when even one question is unacceptable, and says which', function () {
    $problems = ($this->problems)([
        ($this->mcq)(),
        ($this->mcq)(['answer' => 5]),
        ($this->mcq)(['question' => '']),
    ]);

    expect($problems)->toHaveCount(2)
        ->and($problems[0])->toStartWith('Question 2:')
        ->and($problems[1])->toStartWith('Question 3:');
    expect(Question::count())->toBe(0);
});

it('refuses a malformed question', function (array $question) {
    expect(($this->problems)([$question]))->not->toBeEmpty();
    expect(Question::count())->toBe(0);
})->with([
    'unknown type' => [['type' => 'true_false', 'question' => 'ক?', 'options' => ['ক', 'খ'], 'answer' => 1]],
    'one option only' => [['question' => 'ক?', 'options' => ['ক'], 'answer' => 1]],
    'seven options' => [['question' => 'ক?', 'options' => ['১', '২', '৩', '৪', '৫', '৬', '৭'], 'answer' => 1]],
    'blank option' => [['question' => 'ক?', 'options' => ['ক', ' '], 'answer' => 1]],
    'no answer' => [['question' => 'ক?', 'options' => ['ক', 'খ']]],
    'answer zero' => [['question' => 'ক?', 'options' => ['ক', 'খ'], 'answer' => 0]],
    'unknown difficulty' => [['question' => 'ক?', 'options' => ['ক', 'খ'], 'answer' => 1, 'difficulty' => 'brutal']],
    'marks not in halves' => [['question' => 'ক?', 'options' => ['ক', 'খ'], 'answer' => 1, 'marks' => 1.3]],
    'cq with three parts' => [['type' => 'cq', 'question' => 'ক', 'parts' => [['text' => 'ক', 'marks' => 1], ['text' => 'খ', 'marks' => 2], ['text' => 'গ', 'marks' => 3]]]],
    'cq part without marks' => [['type' => 'cq', 'question' => 'ক', 'parts' => [['text' => 'ক'], ['text' => 'খ', 'marks' => 2], ['text' => 'গ', 'marks' => 3], ['text' => 'ঘ', 'marks' => 4]]]],
]);

it('refuses text that is not valid JSON and hints at the backslash', function () {
    // \sqrt with a single backslash is not a JSON escape at all.
    $problems = ($this->problems)('[{"question": "$\sqrt{4}$ কত?", "options": ["2", "4"], "answer": 1}]');

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain('not valid JSON');
});

it('finds what is wrong with a formula', function (string $text) {
    expect(QuestionImportText::problemsIn($text))->toHaveCount(1);
})->with([
    'single backslash turned into a form feed' => ["\$\frac{1}{2}\$"],
    'single backslash turned into a tab' => ["\$2 \times 3\$"],
    'single backslash turned into a line break' => ["\$a \neq b\$"],
    'a $ without its partner' => ['দাম $5 টাকা'],
    'an empty formula' => ['মান $ $ কত'],
    'unbalanced brackets' => ['$\frac{1}{2$'],
    'left without right' => ['$\left( x + 1$'],
    'begin without end' => ['$\begin{matrix} a & b$'],
    'mismatched environments' => ['$\begin{matrix} a \end{pmatrix}$'],
    'Bangla inside a formula' => ['$x = পাঁচ$'],
    'too long' => ['$'.str_repeat('x', QuestionImportText::MAX_FORMULA_LENGTH + 1).'$'],
]);

it('finds nothing wrong with well-formed text', function (string $text) {
    expect(QuestionImportText::problemsIn($text))->toBe([]);
})->with([
    'plain Bangla' => ['সংখ্যা প্রকাশের প্রতীককে কী বলে?'],
    'several lines' => ["প্রথম লাইন\nদ্বিতীয় লাইন"],
    'inline formulas' => ['$x^2$ ও $\frac{a}{b}$ এর যোগফল'],
    'double dollars' => ['$$\left( \frac{1}{2} \right)^2$$'],
    'arrows are not \left or \right' => ['$A \leftarrow B \rightarrow C \leftrightarrow D$'],
    'escaped brackets and line breaks' => ['$\{1, 2\} \\\\ \begin{cases} a \\\\ b \end{cases}$'],
    'a literal dollar sign' => ['দাম \$5'],
]);

it('refuses more questions than the admin allows at once', function () {
    BillingSetting::current()->fill(['question_import_max' => 2])->save();

    $problems = ($this->problems)([($this->mcq)(), ($this->mcq)(['question' => 'দুই?']), ($this->mcq)(['question' => 'তিন?'])]);

    expect($problems)->toHaveCount(1);
    expect(Question::count())->toBe(0);
});

it('allows one hundred questions at once until the admin says otherwise', function () {
    expect(BillingSetting::current()->question_import_max)->toBe(100);
});

it('refuses a topic of another chapter', function () {
    $otherTopic = Topic::create(['chapter_id' => Chapter::factory()->create()->id, 'name' => 'Other', 'order_index' => 1]);

    expect(fn () => ($this->import)([($this->mcq)()], $otherTopic->id))->toThrow(ValidationException::class);
    expect(Question::count())->toBe(0);
});

it('leaves out a question the user already has in the chapter, or that is pasted twice', function () {
    ($this->import)([($this->mcq)()]);

    $result = ($this->import)([($this->mcq)(), ($this->mcq)(['question' => 'নতুন?']), ($this->mcq)(['question' => 'নতুন?'])]);

    expect($result)->toBe(['added' => 1, 'skipped' => 2]);
    expect(Question::count())->toBe(2);
});

it('does not treat someone else\'s pending question as already there', function () {
    $otherTeacher = User::factory()->teacher()->create();
    Question::factory()->for($this->chapter)->create(['created_by' => $otherTeacher->id, 'question_text' => '<p>HTML এর পূর্ণরূপ কী?</p>']);

    expect(($this->import)([($this->mcq)()]))->toBe(['added' => 1, 'skipped' => 0]);
});

it('does not let staff or a suspended teacher import', function (User $user) {
    $this->actingAs($user);

    expect(fn () => ($this->import)([($this->mcq)()]))->toThrow(AuthorizationException::class);
    expect(Question::count())->toBe(0);
})->with([
    'staff' => fn () => User::factory()->staff()->create(),
    'suspended teacher' => fn () => User::factory()->teacher()->suspended()->create(),
]);

it('adds questions through the "Add from JSON" button of a chapter\'s question list', function () {
    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertActionVisible('importQuestionsFromJson')
        ->callAction('importQuestionsFromJson', data: [
            'difficulty' => Difficulty::Hard->value,
            'json' => json_encode([($this->mcq)(), ($this->cq)()]),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Question::count())->toBe(2)
        ->and(Question::where('difficulty', Difficulty::Hard)->count())->toBe(2);
});

it('shows every problem of the JSON on the button\'s form and adds nothing', function () {
    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction('importQuestionsFromJson', data: [
            'json' => json_encode([($this->mcq)(['answer' => 9]), ($this->mcq)(['question' => '$x^{2$'])]),
        ])
        ->assertHasFormErrors(['json']);

    expect(Question::count())->toBe(0);
});

it('adds nothing while the browser reports a formula it could not draw', function () {
    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction('importQuestionsFromJson', data: [
            'json' => json_encode([($this->mcq)()]),
            'unrenderable_formulas' => 1,
        ])
        ->assertHasFormErrors(['unrenderable_formulas']);

    expect(Question::count())->toBe(0);
});

it('hides the "Add from JSON" button from staff', function () {
    $this->actingAs(User::factory()->staff()->create());

    livewire(StaffListQuestions::class, ['chapter' => $this->chapter->id])
        ->assertActionHidden('importQuestionsFromJson');
});

<?php

use App\Filament\Teacher\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\EditQuestion;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\User;
use App\Services\DuplicateQuestionFinder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
    Filament::setCurrentPanel('teacher');
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    $this->chapter = Chapter::factory()->create();
    $classSubject = ClassSubject::find($this->chapter->class_subject_id);

    $this->picture = fn (string $name): array => [UploadedFile::fake()->image($name, 40, 40)];
    $this->form = fn (array $options): array => [
        'academic_class_id' => $classSubject->academic_class_id,
        'class_subject_id' => $classSubject->id,
        'chapter_id' => $this->chapter->id,
        'question_type' => 'mcq',
        'editor_mode' => 'ckeditor',
        'difficulty' => 'easy',
        'question_text' => '<p>Which one is a triangle?</p>',
        'marks' => 1,
        'options' => $options,
    ];
    $this->pictureOptions = fn (): array => [
        ['option' => '', 'has_image' => true, 'image' => ($this->picture)('circle.png'), 'is_correct' => false],
        ['option' => '', 'has_image' => true, 'image' => ($this->picture)('triangle.png'), 'is_correct' => true],
        ['option' => '', 'has_image' => true, 'image' => ($this->picture)('square.png'), 'is_correct' => false],
        ['option' => '', 'has_image' => true, 'image' => ($this->picture)('star.png'), 'is_correct' => false],
    ];
});

it('saves an MCQ whose options are only pictures', function () {
    livewire(CreateQuestion::class)
        ->fillForm(($this->form)(($this->pictureOptions)()))
        ->call('create')
        ->assertHasNoFormErrors();

    $options = Question::query()->sole()->options;

    expect($options)->toHaveCount(4)
        ->and(collect($options)->every(fn (array $option): bool => filled($option['image']) && blank($option['option'])))->toBeTrue()
        ->and(collect($options)->where('is_correct', true)->count())->toBe(1);
    collect($options)->each(fn (array $option) => Storage::disk('public')->assertExists($option['image']));
});

it('checks a picture-only question for duplicates while the form holds its pictures', function () {
    Question::factory()->approved()->for($this->chapter)->create(['question_text' => '<p>Which one is a triangle?</p>']);

    livewire(CreateQuestion::class)->fillForm(($this->form)(($this->pictureOptions)()))->call('create')->assertHasNoFormErrors();
    $question = Question::query()->where('created_by', $this->teacher->id)->sole();

    livewire(EditQuestion::class, ['record' => $question->id])
        ->fillForm(['question_text' => '<p>Which one is a triangle, really?</p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($question->fresh()->question_text)->toBe('<p>Which one is a triangle, really?</p>');
});

it('still needs the text of an option that has no picture', function () {
    livewire(CreateQuestion::class)
        ->fillForm(($this->form)([
            ['option' => '', 'is_correct' => true],
            ['option' => '<p>Four</p>', 'is_correct' => false],
        ]))
        ->call('create')
        ->assertHasFormErrors();

    expect(Question::count())->toBe(0);
});

it('keeps picture-only options when the question is edited', function () {
    livewire(CreateQuestion::class)->fillForm(($this->form)(($this->pictureOptions)()))->call('create')->assertHasNoFormErrors();
    $question = Question::query()->sole();

    livewire(EditQuestion::class, ['record' => $question->id])
        ->fillForm(['question_text' => '<p>Pick the triangle.</p>'])
        ->call('save')
        ->assertHasNoFormErrors();

    $question->refresh();
    expect($question->question_text)->toBe('<p>Pick the triangle.</p>')
        ->and(collect($question->options)->pluck('image')->filter()->count())->toBe(4);
});

it('never takes two picture-only questions with the same text for the same question', function () {
    $finder = new DuplicateQuestionFinder(85);
    $pictures = fn (string ...$images): array => array_map(fn (string $image, int $i): array => ['option' => '', 'image' => $image, 'is_correct' => $i === 0], $images, array_keys($images));

    $first = Question::factory()->for($this->chapter)->make(['options' => $pictures('a.png', 'b.png', 'c.png', 'd.png')]);
    $other = Question::factory()->for($this->chapter)->make(['question_text' => $first->question_text, 'options' => $pictures('e.png', 'f.png', 'g.png', 'h.png')]);
    $same = Question::factory()->for($this->chapter)->make(['question_text' => $first->question_text, 'options' => $pictures('d.png', 'c.png', 'b.png', 'a.png')]);

    expect($finder->compare($other, $first))->toBeNull()
        ->and($finder->compare($same, $first))->not->toBeNull();
});

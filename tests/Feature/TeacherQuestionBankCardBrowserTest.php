<?php

use App\Filament\Teacher\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Teacher\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Teacher\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Teacher\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions;
use App\Filament\Teacher\Resources\Questions\QuestionResource;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);

    $this->class = AcademicClass::factory()->create(['name' => 'Class 9']);
    $this->classSubject = ClassSubject::create([
        'academic_class_id' => $this->class->id,
        'subject_id' => Subject::factory()->create(['name' => 'Physics'])->id,
        'order_index' => 1,
    ]);
    $this->chapter = Chapter::create(['class_subject_id' => $this->classSubject->id, 'name' => 'Motion', 'order_index' => 1]);
});

it('opens "My questions" on the class cards and drills down to a chapter\'s question list', function () {
    $this->get(QuestionResource::getUrl('index', panel: 'teacher'))
        ->assertOk()
        ->assertSee('Class 9');

    $this->get(QuestionResource::getUrl('subjects', ['class' => $this->class->id], panel: 'teacher'))
        ->assertOk()
        ->assertSee('Physics');

    $this->get(QuestionResource::getUrl('chapters', ['class' => $this->class->id, 'classSubject' => $this->classSubject->id], panel: 'teacher'))
        ->assertOk()
        ->assertSee('Motion');

    $this->get(QuestionResource::getUrl('list', ['chapter' => $this->chapter->id], panel: 'teacher'))
        ->assertOk();
});

it('counts only the teacher\'s own questions on the subject and chapter cards', function () {
    Question::factory()->for($this->chapter)->count(2)->create(['created_by' => $this->teacher->id]);

    // Someone else's questions — approved or not — are not behind these cards.
    $otherTeacher = User::factory()->teacher()->create();
    Question::factory()->for($this->chapter)->approved()->count(3)->create(['created_by' => $otherTeacher->id]);
    Question::factory()->for($this->chapter)->create(['created_by' => $otherTeacher->id]);

    $subjectsHtml = livewire(BrowseSubjects::class, ['class' => $this->class->id])->html();
    expect($subjectsHtml)->toContain('1 Chapter');
    expect($subjectsHtml)->toContain('2 Questions');

    $chaptersHtml = livewire(BrowseChapters::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id])->html();
    expect($chaptersHtml)->toContain('2 Questions');
});

it('never shows the class/subject/chapter management controls to a teacher', function () {
    livewire(BrowseClasses::class)->assertDontSee('Add class');
    livewire(BrowseSubjects::class, ['class' => $this->class->id])->assertDontSee('Attach subject');
    livewire(BrowseChapters::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id])->assertDontSee('Add chapter');
});

it('builds the class → subject → chapter breadcrumb trail for a teacher', function () {
    $listBreadcrumbs = livewire(ListQuestions::class, ['chapter' => $this->chapter->id])->instance()->getBreadcrumbs();

    expect(array_values($listBreadcrumbs))->toBe(['Classes', 'Class 9', 'Physics', 'Motion — Questions']);
});

it('pre-selects class, subject, and chapter when a teacher arrives from a chapter\'s "add question" link', function () {
    Livewire::withQueryParams(['chapter' => $this->chapter->id]);

    livewire(CreateQuestion::class)
        ->assertFormSet([
            'academic_class_id' => $this->class->id,
            'class_subject_id' => $this->classSubject->id,
            'chapter_id' => $this->chapter->id,
        ]);
});

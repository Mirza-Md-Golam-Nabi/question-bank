<?php

use App\Filament\Staff\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Staff\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Staff\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Staff\Resources\Questions\Pages\CreateQuestion;
use App\Filament\Staff\Resources\Questions\Pages\ListQuestions;
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
    $this->actingAs(User::factory()->staff()->create());
});

it('renders the class, subject, and chapter card browsers with correct counts', function () {
    $class = AcademicClass::factory()->create(['name' => 'Class 9']);
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'order_index' => 1]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Motion', 'order_index' => 1]);
    Question::factory()->for($chapter)->approved()->count(3)->create();

    $classesHtml = livewire(BrowseClasses::class)->html();
    expect($classesHtml)->toContain('Class 9');
    expect($classesHtml)->toContain('1 Subject');

    $subjectsHtml = livewire(BrowseSubjects::class, ['class' => $class->id])->html();
    expect($subjectsHtml)->toContain('Physics');
    expect($subjectsHtml)->toContain('1 Chapter');
    expect($subjectsHtml)->toContain('3 Questions');

    $chaptersHtml = livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->html();
    expect($chaptersHtml)->toContain('Motion');
    expect($chaptersHtml)->toContain('3 Questions');
});

it('builds a full class → subject → chapter breadcrumb trail for staff too', function () {
    $class = AcademicClass::factory()->create(['name' => 'Class 9']);
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'order_index' => 1]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Motion', 'order_index' => 1]);

    $chaptersBreadcrumbs = livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->instance()->getBreadcrumbs();
    expect(array_values($chaptersBreadcrumbs))->toBe(['Classes', 'Class 9', 'Physics — Chapters']);

    $listBreadcrumbs = livewire(ListQuestions::class, ['chapter' => $chapter->id])->instance()->getBreadcrumbs();
    expect(array_values($listBreadcrumbs))->toBe(['Classes', 'Class 9', 'Physics', 'Motion — Questions']);
});

it('never shows the class/subject/chapter management controls to staff', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    livewire(BrowseClasses::class)->assertDontSee('Add class');
    livewire(BrowseSubjects::class, ['class' => $class->id])->assertDontSee('Attach subject');
    livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->assertDontSee('Add chapter');
});

it('refuses to run the admin-only class/subject/chapter management actions for staff', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);

    expect(fn () => livewire(BrowseClasses::class)->callAction('createClass', data: ['name' => 'Class 10', 'order_index' => 1]))
        ->toThrow(Exception::class);

    expect(fn () => livewire(BrowseSubjects::class, ['class' => $class->id])->callAction('attachSubject', data: ['subject_id' => $subject->id, 'order_index' => 1]))
        ->toThrow(Exception::class);

    expect(fn () => livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->callAction('createChapter', data: ['name' => 'Chapter 1', 'order_index' => 1]))
        ->toThrow(Exception::class);
});

it('pre-selects class, subject, and chapter when a staff member arrives from a chapter\'s "add question" link', function () {
    $class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Motion', 'order_index' => 1]);

    Livewire::withQueryParams(['chapter' => $chapter->id]);

    livewire(CreateQuestion::class)
        ->assertFormSet([
            'academic_class_id' => $class->id,
            'class_subject_id' => $classSubject->id,
            'chapter_id' => $chapter->id,
        ]);
});

it('shows 4 default mcq option rows on the create question page, same as the admin panel', function () {
    $test = livewire(CreateQuestion::class);
    $test->html();

    expect($test->instance()->data['options'] ?? [])->toHaveCount(4);
});

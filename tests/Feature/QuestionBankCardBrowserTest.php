<?php

use App\Filament\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Resources\Questions\Pages\BrowseClasses;
use App\Filament\Resources\Questions\Pages\BrowseSubjects;
use App\Filament\Resources\Questions\Pages\ListQuestions;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
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
    expect($classesHtml)->toContain('Add class');

    $subjectsHtml = livewire(BrowseSubjects::class, ['class' => $class->id])->html();
    expect($subjectsHtml)->toContain('Physics');
    expect($subjectsHtml)->toContain('1 Chapter');
    expect($subjectsHtml)->toContain('3 Questions');
    expect($subjectsHtml)->toContain('Attach subject');

    $chaptersHtml = livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->html();
    expect($chaptersHtml)->toContain('Motion');
    expect($chaptersHtml)->toContain('3 Questions');
    expect($chaptersHtml)->toContain('Add chapter');
});

it('builds a full class → subject → chapter breadcrumb trail at every drill-down level', function () {
    $class = AcademicClass::factory()->create(['name' => 'Class 9']);
    $subject = Subject::factory()->create(['name' => 'Physics']);
    $classSubject = ClassSubject::create(['academic_class_id' => $class->id, 'subject_id' => $subject->id, 'order_index' => 1]);
    $chapter = Chapter::create(['class_subject_id' => $classSubject->id, 'name' => 'Motion', 'order_index' => 1]);

    $subjectsBreadcrumbs = livewire(BrowseSubjects::class, ['class' => $class->id])->instance()->getBreadcrumbs();
    expect(array_values($subjectsBreadcrumbs))->toBe(['Classes', 'Class 9 — Subjects']);

    $chaptersBreadcrumbs = livewire(BrowseChapters::class, ['class' => $class->id, 'classSubject' => $classSubject->id])->instance()->getBreadcrumbs();
    expect(array_values($chaptersBreadcrumbs))->toBe(['Classes', 'Class 9', 'Physics — Chapters']);

    $listBreadcrumbs = livewire(ListQuestions::class, ['chapter' => $chapter->id])->instance()->getBreadcrumbs();
    expect(array_values($listBreadcrumbs))->toBe(['Classes', 'Class 9', 'Physics', 'Motion — Questions']);

    // Every non-final crumb must carry a real, distinct URL to jump straight
    // to that level — not just a label repeated with no link.
    $listKeys = array_keys($listBreadcrumbs);
    expect($listKeys)->toHaveCount(4);
    expect(array_slice($listKeys, 0, 3))->each(fn ($key) => $key->not->toBeInt());
    expect(array_unique(array_slice($listKeys, 0, 3)))->toHaveCount(3);
});

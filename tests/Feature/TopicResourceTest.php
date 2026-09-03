<?php

use App\Filament\Resources\Questions\Pages\BrowseChapters;
use App\Filament\Resources\Questions\Pages\BrowseTopics;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RoleSeeder;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->class = AcademicClass::factory()->create();
    $subject = Subject::factory()->create();
    $this->classSubject = ClassSubject::create(['academic_class_id' => $this->class->id, 'subject_id' => $subject->id]);
    $this->chapter = Chapter::create(['class_subject_id' => $this->classSubject->id, 'name' => 'Algebra', 'order_index' => 1]);
});

it('creates a topic scoped to the current chapter', function () {
    livewire(BrowseTopics::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $this->chapter->id])
        ->callAction('createTopic', data: ['name' => 'Quadratic Equations', 'order_index' => 1])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('topics', ['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations']);
});

it('shows how many questions each topic has', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations', 'order_index' => 1]);
    Question::factory()->for($this->chapter)->approved()->create(['topic_id' => $topic->id]);

    livewire(BrowseTopics::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $this->chapter->id])
        ->assertSee($topic->name)
        ->assertSee('1 Question');
});

it('edits a topic', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Old name', 'order_index' => 1]);

    livewire(BrowseTopics::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $this->chapter->id])
        ->callAction('editTopic', data: ['name' => 'New name', 'order_index' => 2], arguments: ['topic' => $topic->id])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('topics', ['id' => $topic->id, 'name' => 'New name']);
});

it('deletes a topic', function () {
    $topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Quadratic Equations', 'order_index' => 1]);

    livewire(BrowseTopics::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $this->chapter->id])
        ->callAction('deleteTopic', arguments: ['topic' => $topic->id]);

    $this->assertModelMissing($topic);
});

it('shifts later topics down when a new topic is inserted at their order', function () {
    $topic1 = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Topic 1', 'order_index' => 1]);
    $topic2 = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Topic 2', 'order_index' => 2]);
    $topic3 = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Topic 3', 'order_index' => 3]);

    livewire(BrowseTopics::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $this->chapter->id])
        ->callAction('createTopic', data: ['name' => 'Topic 1.5', 'order_index' => 2])
        ->assertHasNoActionErrors();

    expect($topic1->refresh()->order_index)->toBe(1);
    expect($topic2->refresh()->order_index)->toBe(3);
    expect($topic3->refresh()->order_index)->toBe(4);
    $this->assertDatabaseHas('topics', ['name' => 'Topic 1.5', 'order_index' => 2]);
});

it('links to the topics page from the chapter browser', function () {
    livewire(BrowseChapters::class, ['class' => $this->class->id, 'classSubject' => $this->classSubject->id])
        ->assertSee('Topics');
});

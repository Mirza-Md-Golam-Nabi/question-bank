<?php

use App\Models\Chapter;
use App\Models\Question;
use App\Models\Topic;

it('belongs to a chapter and lists its questions', function () {
    $chapter = Chapter::factory()->create();
    $topic = Topic::factory()->for($chapter)->create(['name' => 'Trigonometry', 'order_index' => 1]);
    $question = Question::factory()->for($chapter)->create(['topic_id' => $topic->id]);

    expect($topic->chapter->is($chapter))->toBeTrue();
    expect($chapter->topics)->toHaveCount(1);
    expect($topic->questions->pluck('id'))->toContain($question->id);
});

it('lets a question be created without a topic', function () {
    $question = Question::factory()->create(['topic_id' => null]);

    expect($question->topic_id)->toBeNull();
    expect($question->topic)->toBeNull();
});

it('lets a question be created with a topic', function () {
    $chapter = Chapter::factory()->create();
    $topic = Topic::factory()->for($chapter)->create();
    $question = Question::factory()->for($chapter)->create(['topic_id' => $topic->id]);

    expect($question->topic->is($topic))->toBeTrue();
});

it('nulls out a question\'s topic_id when that topic is deleted, without deleting the question', function () {
    $chapter = Chapter::factory()->create();
    $topic = Topic::factory()->for($chapter)->create();
    $question = Question::factory()->for($chapter)->create(['topic_id' => $topic->id]);

    $topic->delete();

    expect($question->refresh()->topic_id)->toBeNull();
    $this->assertModelExists($question);
});

it('orders topics by order_index', function () {
    $chapter = Chapter::factory()->create();
    $second = Topic::factory()->for($chapter)->create(['order_index' => 2]);
    $first = Topic::factory()->for($chapter)->create(['order_index' => 1]);

    expect($chapter->topics()->ordered()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

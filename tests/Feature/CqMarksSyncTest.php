<?php

use App\Enums\CqPartType;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
});

it('sums the cq parts marks into the parent question as parts are created, updated, and deleted', function () {
    $question = Question::factory()->cq()->for(Chapter::factory())->create();

    expect($question->marks)->toEqual(0);

    $knowledge = $question->cqParts()->create([
        'part_type' => CqPartType::Knowledge,
        'part_order' => 1,
        'part_text' => '<p>K</p>',
        'marks' => 1,
    ]);

    expect($question->refresh()->marks)->toEqual(1);

    $comprehension = $question->cqParts()->create([
        'part_type' => CqPartType::Comprehension,
        'part_order' => 2,
        'part_text' => '<p>C</p>',
        'marks' => 2,
    ]);

    expect($question->refresh()->marks)->toEqual(3);

    $knowledge->update(['marks' => 5]);

    expect($question->refresh()->marks)->toEqual(7);

    $comprehension->delete();

    expect($question->refresh()->marks)->toEqual(5);
});

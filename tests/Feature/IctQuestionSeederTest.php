<?php

use App\Enums\QuestionStatus;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\AcademicClassSeeder;
use Database\Seeders\IctQuestionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubjectSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    User::factory()->admin()->create();
});

it('does not seed questions outside the local environment', function () {
    $this->seed(IctQuestionSeeder::class);

    expect(Question::count())->toBe(0);
});

it('seeds approved mcq questions into three ict chapters, each with exactly one correct option', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed([AcademicClassSeeder::class, SubjectSeeder::class, IctQuestionSeeder::class]);

    expect(Chapter::orderBy('order_index')->pluck('order_index')->all())->toBe([3, 4, 5]);
    expect(Chapter::withCount('questions')->orderBy('order_index')->pluck('questions_count')->all())->toBe([9, 10, 10]);

    Question::all()->each(function (Question $question) {
        expect($question->status)->toBe(QuestionStatus::Approved);
        expect($question->is_latest)->toBeTrue();
        expect($question->options)->toHaveCount(4);
        expect(collect($question->options)->where('is_correct', true))->toHaveCount(1);
    });
});

it('stores html tags in the questions as text, not as markup', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed([AcademicClassSeeder::class, SubjectSeeder::class, IctQuestionSeeder::class]);

    $question = Question::where('question_text', 'like', '%সবচেয়ে বড় heading%')->sole();

    expect(collect($question->options)->firstWhere('is_correct', true)['option'])->toBe('<p>&lt;h1&gt;</p>');
});

it('does not duplicate questions when run again', function () {
    app()->detectEnvironment(fn () => 'local');
    $this->seed([AcademicClassSeeder::class, SubjectSeeder::class, IctQuestionSeeder::class]);
    $this->seed(IctQuestionSeeder::class);

    expect(Question::count())->toBe(29);
});

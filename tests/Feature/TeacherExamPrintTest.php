<?php

use App\Enums\ExamDeliveryMode;
use App\Enums\QuestionType;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\BillingSetting;
use App\Models\BoardMcqQuestion;
use App\Models\Chapter;
use App\Models\Exam;
use App\Models\InstitutionName;
use App\Models\Question;
use App\Models\User;
use App\Support\PrintSettings;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->teacher = User::factory()->teacher()->create();
    $this->subscription = subscribeToEverything($this->teacher);
    $this->actingAs($this->teacher);

    $this->examWith = function (ExamDeliveryMode $mode): Exam {
        $exam = Exam::factory()->delivery($mode)->create(['created_by' => $this->teacher->id, 'title' => 'Half yearly']);
        $chapter = Chapter::factory()->create();

        $mcq = Question::factory()->approved()->for($chapter)->create([
            'question_text' => '<p>Capital of Bangladesh?</p>',
            'options' => [
                ['option' => 'Dhaka', 'image' => null, 'is_correct' => true],
                ['option' => 'Khulna', 'image' => null, 'is_correct' => false],
            ],
        ]);
        $cq = Question::factory()->cq()->approved()->for($chapter)->create(['question_text' => '<p>Read the stimulus.</p>']);
        $cq->cqParts()->create(['part_type' => 'knowledge', 'part_order' => 1, 'part_text' => '<p>What is a cell?</p>', 'marks' => 4]);

        $exam->questions()->attach([
            $mcq->id => ['order_index' => 1, 'marks_override' => null],
            $cq->id => ['order_index' => 2, 'marks_override' => null],
        ]);

        return $exam;
    };
});

it('prints every question of an offline exam without marking the answers', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);

    $this->get(route('filament.teacher.exams.print', $exam))
        ->assertOk()
        ->assertSee('Half yearly')
        ->assertSee('Capital of Bangladesh?')
        ->assertSee('Read the stimulus.')
        ->assertSee('What is a cell?')
        ->assertDontSee('class="qb-question-option qb-question-option--correct"', escape: false);
});

it('marks the correct mcq option on the answer copy', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Both);

    $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 1]))
        ->assertOk()
        ->assertSee('class="qb-question-option qb-question-option--correct"', escape: false);
});

it('prints the answer key alone: each mcq number and its right option\'s letter', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);
    $second = Question::factory()->approved()->create([
        'options' => [
            ['option' => 'Rice', 'image' => null, 'is_correct' => false],
            ['option' => 'Hilsa', 'image' => null, 'is_correct' => true],
        ],
    ]);
    $exam->questions()->attach($second->id, ['order_index' => 3, 'marks_override' => null]);

    $html = $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 'key']))
        ->assertOk()
        ->assertSee('Answer key')
        ->assertDontSee('Capital of Bangladesh?')
        ->assertDontSee('What is a cell?')
        ->getContent();

    preg_match_all('/qb-answer-key-number">(\d*)<.*?qb-answer-key-answer">([^<]*)</s', $html, $cells, PREG_SET_ORDER);

    expect(collect($cells)->filter(fn (array $cell): bool => $cell[1] !== '')->mapWithKeys(fn (array $cell): array => [$cell[1] => $cell[2]])->all())
        ->toBe([1 => 'A', 2 => 'B']);
});

it('offers no answer key for a paper with no mcq', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);
    $exam->questions()->where('question_type', 'mcq')->get()->each(fn (Question $question) => $exam->questions()->detach($question->id));

    $this->get(route('filament.teacher.exams.print', $exam))->assertOk()->assertDontSee('Answer key only');
    $this->get(route('filament.teacher.exams.print', ['exam' => $exam, 'answers' => 'key']))->assertNotFound();
});

it('offers "print answer key only" in the exam list only for a printed paper with mcq', function () {
    Filament::setCurrentPanel(Filament::getPanel('teacher'));

    $offline = ($this->examWith)(ExamDeliveryMode::Offline);
    $online = ($this->examWith)(ExamDeliveryMode::Online);
    $cqOnly = ($this->examWith)(ExamDeliveryMode::Offline);
    $cqOnly->questions()->where('question_type', 'mcq')->get()->each(fn (Question $question) => $cqOnly->questions()->detach($question->id));

    livewire(ListExams::class)
        ->assertTableActionVisible('printAnswerKey', $offline)
        ->assertTableActionHasUrl('printAnswerKey', route('filament.teacher.exams.print', ['exam' => $offline, 'answers' => 'key']), $offline)
        ->assertTableActionHidden('printAnswerKey', $online)
        ->assertTableActionHidden('printAnswerKey', $cqOnly);
});

it('lists a past paper\'s several right options, or a dash when it gives none', function () {
    $several = BoardMcqQuestion::factory()->make(['options' => [
        ['option' => 'a', 'image' => null, 'is_correct' => true],
        ['option' => 'b', 'image' => null, 'is_correct' => false],
        ['option' => 'c', 'image' => null, 'is_correct' => true],
    ]]);
    $none = BoardMcqQuestion::factory()->make(['options' => [
        ['option' => 'a', 'image' => null, 'is_correct' => false],
        ['option' => 'b', 'image' => null, 'is_correct' => false],
    ]]);

    $html = view('teacher.partials.exam-answer-key', ['mcqQuestions' => collect([$several, $none])])->render();

    expect($html)->toContain('qb-answer-key-answer">A, C<')
        ->toContain('qb-answer-key-answer">—<')
        ->toContain('this question has no correct answer');
});

it('has no printable paper for an online-only exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Online);

    $this->get(route('filament.teacher.exams.print', $exam))->assertNotFound();
});

it('does not let another teacher print the exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);

    $this->actingAs(User::factory()->teacher()->create());

    $this->get(route('filament.teacher.exams.print', $exam))->assertForbidden();
});

it('leaves cq questions out of the online attempt of an online + offline exam', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Both);
    $exam->recalculateTotalMarks();
    $exam->publish();

    auth()->logout();

    $this->followingRedirects()
        ->post(route('guest-exam.start', $exam->share_token), ['guest_name' => 'Rahim', 'guest_contact' => '01700000001'])
        ->assertOk()
        ->assertSee('Capital of Bangladesh?')
        ->assertDontSee('Read the stimulus.');

    // Scored out of the MCQ marks only, since that is all a student sees.
    expect((float) $exam->fresh()->total_marks)->toBe(1.0);
});

describe('print settings', function () {
    it('lays the paper out by the teacher\'s own saved settings', function () {
        $exam = ($this->examWith)(ExamDeliveryMode::Offline);
        $this->teacher->forceFill(['print_settings' => ['columns' => 3, 'paper_size' => 'Legal']])->save();

        $settings = $this->get(route('filament.teacher.exams.print', $exam))
            ->assertOk()
            ->viewData('printSettings');

        expect($settings['columns'])->toBe(3)
            ->and($settings['paper_size'])->toBe('Legal')
            ->and($settings['font_size'])->toBe(PrintSettings::DEFAULTS['font_size']);
    });

    it('falls back to the default for a saved value that is no longer allowed', function () {
        $this->teacher->forceFill(['print_settings' => ['columns' => 7, 'unknown' => 'x']])->save();

        expect(PrintSettings::for($this->teacher->refresh()))->toBe(PrintSettings::DEFAULTS);
    });

    it('saves the settings the teacher changes', function () {
        $this->postJson(route('filament.teacher.exams.print-settings'), ['columns' => 1, 'column_divider' => false, 'mcq_instructions' => 'Answer all.'])
            ->assertNoContent();

        $settings = PrintSettings::for($this->teacher->refresh());

        expect($settings['columns'])->toBe(1)
            ->and($settings['column_divider'])->toBeFalse()
            ->and($settings['mcq_instructions'])->toBe('Answer all.')
            ->and($settings['paper_size'])->toBe('A4');
    });

    it('refuses a setting out of its limits', function () {
        $this->postJson(route('filament.teacher.exams.print-settings'), ['columns' => 5, 'paper_size' => 'B5'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['columns', 'paper_size']);
    });

    it('only offers the names that can be printed now', function () {
        $exam = ($this->examWith)(ExamDeliveryMode::Offline);
        InstitutionName::factory()->free()->create(['user_id' => $this->teacher->id, 'name' => 'Free School']);
        InstitutionName::factory()->paid()->create(['user_id' => $this->teacher->id, 'name' => 'Paid Coaching']);
        InstitutionName::factory()->expired()->create(['user_id' => $this->teacher->id, 'name' => 'Old Academy']);

        $this->get(route('filament.teacher.exams.print', $exam))
            ->assertOk()
            ->assertSee('Free School')
            ->assertSee('Paid Coaching')
            ->assertDontSee('Old Academy');
    });

    it('prints the admin\'s instructions and the chapters the questions come from', function () {
        $exam = ($this->examWith)(ExamDeliveryMode::Offline);
        BillingSetting::create(['print_mcq_instructions' => 'Fill the right circle.']);

        $response = $this->get(route('filament.teacher.exams.print', $exam))->assertOk();

        expect($response->viewData('defaultInstructions')['mcq'])->toBe('Fill the right circle.')
            ->and($response->viewData('chapterNames'))->toBe([$exam->questions->first()->chapter->name]);
    });
});

it('marks the paper\'s own words as editable, but never the institution name', function () {
    $exam = ($this->examWith)(ExamDeliveryMode::Offline);
    $mcq = $exam->questions->firstWhere('question_type', QuestionType::Mcq);

    $this->get(route('filament.teacher.exams.print', $exam))
        ->assertOk()
        ->assertSee('data-edit-scope="Question-'.$mcq->id.'"', escape: false)
        ->assertSee('data-editable="title"', escape: false)
        ->assertSee('data-editing-toggle', escape: false)
        ->assertDontSee('data-print-institution data-editable', escape: false)
        ->assertSee('qb-print-edits:'.$exam->id, escape: false);
});

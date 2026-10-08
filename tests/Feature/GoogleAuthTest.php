<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Student\Pages\TakeExamPage;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('creates a new active teacher on first google login', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-teacher-1',
        'email' => 'teacher@example.com',
        'name' => 'New Teacher',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $response = $this->get('/auth/google/callback');

    $user = User::where('email', 'teacher@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe(UserRole::Teacher);
    expect($user->status)->toBe(UserStatus::Active);
    expect($user->google_id)->toBe('google-teacher-1');
    expect($user->hasRole(UserRole::Teacher->value))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('filament.teacher.pages.dashboard'));
});

it('creates a new staff account as pending approval on first google login', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-staff-1',
        'email' => 'staff@example.com',
        'name' => 'New Staff',
    ]));

    $this->get('/auth/google/redirect/staff');
    $this->get('/auth/google/callback');

    $user = User::where('email', 'staff@example.com')->first();

    expect($user->role)->toBe(UserRole::Staff);
    expect($user->status)->toBe(UserStatus::Pending);
});

it('logs in an existing user by google_id without creating a duplicate', function () {
    $existing = User::factory()->teacher()->googleAuthenticated()->create([
        'google_id' => 'google-teacher-2',
        'email' => 'existing-teacher@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-teacher-2',
        'email' => 'existing-teacher@example.com',
        'name' => 'Existing Teacher',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $this->get('/auth/google/callback');

    expect(User::where('email', 'existing-teacher@example.com')->count())->toBe(1);
    $this->assertAuthenticatedAs($existing);
});

it('blocks login when the google account role does not match the intended panel role', function () {
    User::factory()->student()->googleAuthenticated()->create([
        'google_id' => 'google-student-1',
        'email' => 'mismatch@example.com',
    ]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'google-student-1',
        'email' => 'mismatch@example.com',
        'name' => 'Mismatch User',
    ]));

    $this->get('/auth/google/redirect/teacher');
    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('filament.teacher.auth.login'));
    $this->assertGuest();
});

it('returns 404 for a google redirect targeting a password-authenticated role', function () {
    $this->get('/auth/google/redirect/admin')->assertNotFound();
    $this->get('/auth/google/redirect/super_admin')->assertNotFound();
    $this->get('/auth/google/redirect/not-a-role')->assertNotFound();
});

describe('logging in from an exam share link', function () {
    beforeEach(function () {
        $this->exam = Exam::factory()->published()->create(['duration_minutes' => 10]);
        $this->joinUrl = route('guest-exam.join', $this->exam->share_token);

        $this->fakeGoogleStudent = fn (string $email = 'student@example.com') => Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-'.$email,
            'email' => $email,
            'name' => 'A Student',
        ]));
    });

    it('takes a logged-out student from the link through google login straight into the exam', function () {
        ($this->fakeGoogleStudent)();

        // 1. "Login to attempt" on the share page.
        $this->get($this->joinUrl)->assertRedirect(route('auth.google.redirect', 'student'));

        // 2. Google login.
        $this->get(route('auth.google.redirect', 'student'));
        $callback = $this->get(route('auth.google.callback'));

        // 3. Back to the exam, not the dashboard.
        $callback->assertRedirect($this->joinUrl);

        $student = User::where('email', 'student@example.com')->sole();
        $this->assertAuthenticatedAs($student);

        $response = $this->get($this->joinUrl);
        $attempt = ExamAttempt::sole();

        expect($attempt->student_id)->toBe($student->id);
        expect($attempt->exam_id)->toBe($this->exam->id);
        $response->assertRedirect(TakeExamPage::getUrl(['attempt' => $attempt->id], panel: 'student'));
    });

    it('puts an already logged-in student straight into the exam', function () {
        $student = User::factory()->student()->create();
        $this->actingAs($student);

        $this->get($this->joinUrl)->assertRedirect(TakeExamPage::getUrl(['attempt' => ExamAttempt::sole()->id], panel: 'student'));
    });

    it('resumes the attempt a student already has under way instead of starting another', function () {
        $this->actingAs(User::factory()->student()->create());

        $this->get($this->joinUrl);
        $this->get($this->joinUrl);

        expect(ExamAttempt::count())->toBe(1);
    });

    it('remembers the exam only for that one login', function () {
        ($this->fakeGoogleStudent)();

        $this->get($this->joinUrl);
        $this->get(route('auth.google.redirect', 'student'));
        $this->get(route('auth.google.callback'))->assertRedirect($this->joinUrl);

        auth()->logout();

        // An ordinary login afterwards lands on the dashboard as usual.
        $this->get(route('auth.google.redirect', 'student'));
        $this->get(route('auth.google.callback'))->assertRedirect(route('filament.student.pages.dashboard'));
    });

    it('does not send a teacher logging in afterwards to the exam', function () {
        Socialite::fake('google', SocialiteUser::fake(['id' => 'google-t', 'email' => 't@example.com', 'name' => 'T']));

        $this->get($this->joinUrl);
        $this->get(route('auth.google.redirect', 'teacher'));

        $this->get(route('auth.google.callback'))->assertRedirect(route('filament.teacher.pages.dashboard'));
    });

    it('sends the student back to the share page when the link is closed, without starting anything', function () {
        $this->exam->update(['is_link_active' => false]);
        $this->actingAs(User::factory()->student()->create());

        $this->get($this->joinUrl)->assertRedirect(route('guest-exam.show', $this->exam->share_token));

        expect(ExamAttempt::count())->toBe(0);
    });

    it('points the share page login button at this flow', function () {
        $this->get(route('guest-exam.show', $this->exam->share_token))
            ->assertOk()
            ->assertSee($this->joinUrl, escape: false);
    });
});

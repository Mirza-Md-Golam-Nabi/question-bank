<?php

use App\Http\Middleware\SetLocale;
use App\Models\Exam;

beforeEach(function () {
    // The site's own language, as on the live site.
    config(['app.locale' => 'bn']);
    app()->setLocale('bn');

    $this->exam = Exam::factory()->published()->create();
    $this->link = route('guest-exam.show', $this->exam->share_token);
});

it('opens a shared exam in Bangla even when the same browser chose English in a panel', function () {
    $this->withSession([SetLocale::SESSION_KEY => 'en'])
        ->get($this->link)
        ->assertOk()
        ->assertSee('<html lang="bn"', false)
        ->assertSee('লগইন করে পরীক্ষা দিন');
});

it('keeps a language chosen on the exam pages to the exam pages', function () {
    $this->get(route('locale.switch', ['locale' => 'en', 'for' => 'exam']))->assertRedirect();

    $this->get($this->link)->assertOk()->assertSee('<html lang="en"', false)->assertSee('Login to attempt');

    expect(session(SetLocale::SESSION_KEY))->toBeNull()
        ->and(session(SetLocale::sessionKey('exam')))->toBe('en');
});

it('shows a way back to the home page on the exam link', function () {
    $this->get($this->link)
        ->assertOk()
        ->assertSee(route('home'))
        ->assertSee('হোম পেজ')
        ->assertSee(route('locale.switch', ['locale' => 'en', 'for' => 'exam']), false);
});

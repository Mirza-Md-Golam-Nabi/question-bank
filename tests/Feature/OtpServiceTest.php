<?php

use App\Models\User;
use App\Services\OtpService;
use App\Services\Sms\SmsSender;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->sms = new class implements SmsSender
    {
        /** @var array<int, array{phone: string, message: string}> */
        public array $sent = [];

        public function send(string $phone, string $message): void
        {
            $this->sent[] = ['phone' => $phone, 'message' => $message];
        }

        public function lastCode(): string
        {
            preg_match('/\d{6}/', end($this->sent)['message'], $code);

            return $code[0];
        }
    };
    $this->app->instance(SmsSender::class, $this->sms);
    $this->otp = app(OtpService::class);
});

it('sends the code to the user\'s phone number', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);

    $this->otp->send($student);

    expect($this->sms->sent)->toHaveCount(1)
        ->and($this->sms->sent[0]['phone'])->toBe('01712345678');
});

it('refuses to send a code to a user with no phone number', function () {
    $student = User::factory()->student()->create();

    expect(fn () => $this->otp->send($student))
        ->toThrow(ValidationException::class, 'Add your phone number to your profile first.');
    expect($this->sms->sent)->toBeEmpty();
});

it('accepts the code it sent only once', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);
    $this->otp->send($student);
    $code = $this->sms->lastCode();

    expect($this->otp->verify($student, $code))->toBeTrue()
        ->and($this->otp->verify($student, $code))->toBeFalse();
});

it('rejects a code after it has expired', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);
    $this->otp->send($student);
    $code = $this->sms->lastCode();

    $this->travel(6)->minutes();

    expect($this->otp->verify($student, $code))->toBeFalse();
});

it('rejects a code sent to a number the user has since changed', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);
    $this->otp->send($student);
    $code = $this->sms->lastCode();

    $student->update(['phone' => '01898765432']);

    expect($this->otp->verify($student, $code))->toBeFalse();
});

it('stops accepting guesses after too many wrong codes', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);
    $this->otp->send($student);
    $code = $this->sms->lastCode();
    $wrongCode = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $attempt) {
        $this->otp->verify($student, $wrongCode);
    }

    expect($this->otp->verify($student, $code))->toBeFalse();
});

it('stops sending codes after too many requests in an hour', function () {
    $student = User::factory()->student()->create(['phone' => '01712345678']);

    foreach (range(1, 5) as $request) {
        $this->otp->send($student);
    }

    expect(fn () => $this->otp->send($student))
        ->toThrow(ValidationException::class, 'Too many codes requested. Try again later.');
    expect($this->sms->sent)->toHaveCount(5);
});

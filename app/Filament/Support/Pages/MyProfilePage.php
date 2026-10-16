<?php

namespace App\Filament\Support\Pages;

use App\Enums\UserRole;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\BillingSetting;
use App\Models\StudentReferral;
use App\Models\User;
use App\Services\DeviceSessionService;
use App\Services\ReferralService;
use App\Services\StudentReferralService;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared by the Teacher, Student and Staff panels' "My Profile" page: the
 * phone number (the one way to actually reach a user who signs in with
 * Google and never reads email), a student's class and referrer, the
 * devices signed in on, and their own code to share. What referring has
 * earned them is on one page per panel (earningsUrl()), not here. All the
 * rules are the services'; this page only shows and collects, and what it
 * shows follows from the signed-in user's role, not from the panel.
 */
abstract class MyProfilePage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.my-profile';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * The student's referral, read once per request (false = not read yet)
     * and again after a code is saved.
     */
    private StudentReferral|false|null $currentReferral = false;

    public function mount(): void
    {
        $this->form->fill(Auth::user()->only(['phone', 'academic_class_id']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('phone')
                    ->label(__('Phone number'))
                    ->helperText($this->phoneHelperText())
                    ->tel()
                    ->placeholder('01XXXXXXXXX')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && User::normalizePhone($value) === null) {
                            $fail(__('Enter a valid mobile number, e.g. 01712345678.'));
                        }
                    }),

                // A student's practice and purchases start from their class.
                Select::make('academic_class_id')
                    ->label(__('My class'))
                    ->options(fn (): array => AcademicClass::options())
                    ->visible(fn (): bool => Auth::user()->role === UserRole::Student)
                    ->required(fn (): bool => Auth::user()->role === UserRole::Student),

                TextInput::make('referral_code')
                    ->label(__('Referral code'))
                    ->helperText(fn (): string => Auth::user()->role === UserRole::Student
                        ? __('Got a code from your teacher or a friend? Enter it here. You can change it for :days day(s); after that it stays for :lock days.', [
                            'days' => BillingSetting::current()->referral_change_days,
                            'lock' => BillingSetting::current()->referral_lock_days,
                        ])
                        : __('Got a code from a friend or your teacher? Enter it before your first purchase.'))
                    ->visible(fn (): bool => $this->canEnterReferralCode())
                    ->maxLength(12),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Auth::user();
        $phone = User::normalizePhone($data['phone'] ?? null);

        if ($phone !== $user->phone) {
            // A different number has not been verified by anyone yet.
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();
        }

        if ($user->role === UserRole::Student) {
            $user->update(['academic_class_id' => $data['academic_class_id']]);
        }

        if (filled($data['referral_code'] ?? null) && ! app(ReferralService::class)->attach($user, $data['referral_code'])) {
            $this->addError('data.referral_code', __('This referral code is not valid.'));

            return;
        }

        $this->form->fill($user->only(['phone', 'academic_class_id']));
        $this->currentReferral = false;

        Notification::make()->title(__('Profile saved'))->success()->send();
    }

    /**
     * Where the panel shows what referring has earned this user — the
     * Referrals & commission page, or a Staff member's earnings page.
     */
    abstract public function earningsUrl(): string;

    public function canEnterReferralCode(): bool
    {
        return app(ReferralService::class)->canEnterCode(Auth::user());
    }

    /**
     * Whether this user has a code to share right now.
     */
    public function canRefer(): bool
    {
        $referrals = app(ReferralService::class);

        return $referrals->isEnabled() && $referrals->canRefer(Auth::user());
    }

    /**
     * The devices this user is signed in on (Teacher and Student only —
     * the roles with a device limit).
     *
     * @return SupportCollection<int, array<string, mixed>>
     */
    public function devices(): SupportCollection
    {
        $devices = app(DeviceSessionService::class);

        return $devices->limitFor(Auth::user()) === null
            ? collect()
            : $devices->devicesOf(Auth::user(), session()->getId());
    }

    public function deviceLimit(): ?int
    {
        return app(DeviceSessionService::class)->limitFor(Auth::user());
    }

    /**
     * Signs one of the user's other devices out — e.g. a phone they no
     * longer have.
     */
    public function signOutDevice(string $sessionId): void
    {
        if ($sessionId === session()->getId()) {
            return;
        }

        app(DeviceSessionService::class)->signOut(Auth::user(), $sessionId);

        Notification::make()->title(__('Signed out of that device'))->success()->send();
    }

    public function settings(): BillingSetting
    {
        return BillingSetting::current();
    }

    /**
     * Who referred this user: a student's current referrer (it changes over
     * time), anyone else's for good.
     */
    public function referrer(): ?User
    {
        return Auth::user()->role === UserRole::Student
            ? $this->studentReferral()?->referrer
            : Auth::user()->referrer;
    }

    /**
     * The referral a student is under right now, if any.
     */
    public function studentReferral(): ?StudentReferral
    {
        if ($this->currentReferral === false) {
            $this->currentReferral = Auth::user()->role === UserRole::Student
                ? app(StudentReferralService::class)->current(Auth::user())
                : null;
        }

        return $this->currentReferral;
    }

    public function referralCode(): string
    {
        return app(ReferralService::class)->codeFor(Auth::user());
    }

    public function referralLink(): string
    {
        return app(ReferralService::class)->linkFor(Auth::user());
    }

    public function rewardPercent(): float
    {
        return app(ReferralService::class)->rewardPercentFor(Auth::user());
    }

    /**
     * Everyone this user brought in: the teachers who signed up with their
     * code and the students who have been under their referral (each once).
     */
    public function referredCount(): int
    {
        $user = Auth::user();

        return $user->referrals()->count() + $user->referredStudents()->distinct()->count('student_id');
    }

    private function phoneHelperText(): string
    {
        // The bonus is extra exams on a plan's monthly limit, so it only
        // means something to the roles that have a plan.
        $bonus = Auth::user()->role->subscriptionTargetRole() !== null ? BillingSetting::current()->phone_bonus_exams : 0;

        return $bonus > 0
            ? __('We use it for offers and important notices. Add it and get :count extra free exam(s) every month.', ['count' => $bonus])
            : __('We use it for offers and important notices.');
    }
}

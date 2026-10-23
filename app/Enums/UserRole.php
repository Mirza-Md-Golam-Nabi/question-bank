<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Staff = 'staff';
    case Student = 'student';
    case School = 'school';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SuperAdmin => __('Super Admin'),
            self::Admin => __('Admin'),
            self::Teacher => __('Teacher'),
            self::Staff => __('Staff'),
            self::Student => __('Student'),
            self::School => __('School'),
        };
    }

    /**
     * Roles that authenticate with a password via Filament's default login
     * (Admin Panel). Every other role is Google OAuth-only.
     *
     * @return array<self>
     */
    public static function passwordAuthenticated(): array
    {
        return [self::SuperAdmin, self::Admin];
    }

    public function isPasswordAuthenticated(): bool
    {
        return in_array($this, self::passwordAuthenticated(), strict: true);
    }

    /**
     * An Admin or the Super Admin — whoever reviews and approves content.
     */
    public function isAdmin(): bool
    {
        return $this === self::SuperAdmin || $this === self::Admin;
    }

    /**
     * The side of the subscription plans this role buys from. Null for the
     * roles that have no subscription at all.
     */
    public function subscriptionTargetRole(): ?SubscriptionTargetRole
    {
        return match ($this) {
            self::Teacher => SubscriptionTargetRole::Teacher,
            self::Student => SubscriptionTargetRole::Student,
            self::School => SubscriptionTargetRole::School,
            default => null,
        };
    }

    /**
     * Whether this role's referral rewards become wallet credit — a Teacher
     * or a Student, who spend it on their own subscription. Staff are paid
     * theirs in cash with their earnings instead; a School takes no part in
     * referrals.
     */
    public function holdsWallet(): bool
    {
        return $this === self::Teacher || $this === self::Student;
    }

    /**
     * Whether a new account of this role waits for an Admin's approval
     * before it can do anything: a Staff member (who is paid) and a School
     * (whose account speaks for a whole school).
     */
    public function needsApproval(): bool
    {
        return $this === self::Staff || $this === self::School;
    }

    /**
     * Whether this role sets exams for others from the question picker —
     * a Teacher for anyone, a School for its own students.
     */
    public function setsExams(): bool
    {
        return $this === self::Teacher || $this === self::School;
    }

    /**
     * The language this role's panel opens in until its user picks one —
     * the Admin panel in English, the rest of the platform in the app's
     * own (Bangla). Also what their bell notifications are written in.
     */
    public function defaultLocale(): Locale
    {
        return $this->isAdmin() ? Locale::En : (Locale::tryFrom((string) config('app.locale')) ?? Locale::Bn);
    }

    /**
     * The Filament panel id this role is allowed into.
     */
    public function panelId(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => 'admin',
            self::Teacher => 'teacher',
            self::Staff => 'staff',
            self::Student => 'student',
            self::School => 'school',
        };
    }
}

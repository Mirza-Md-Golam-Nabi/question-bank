<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'google_id', 'avatar', 'role', 'status', 'academic_class_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role->panelId() === $panel->getId()
            && $this->status !== UserStatus::PermanentSuspend;
    }

    /**
     * The Admins and the Super Admin.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->whereIn('role', array_filter(UserRole::cases(), fn (UserRole $role): bool => $role->isAdmin()));
    }

    /**
     * The Google profile photo, saved at each Google sign-in — what every
     * panel's user menu shows. Without one (an Admin), Filament's initials.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ?: null;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function isPermanentlySuspended(): bool
    {
        return $this->status === UserStatus::PermanentSuspend;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isStaffPendingApproval(): bool
    {
        return $this->role === UserRole::Staff && $this->status === UserStatus::Pending;
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function staffEarnings(): HasMany
    {
        return $this->hasMany(StaffEarning::class, 'staff_id');
    }

    public function staffPayouts(): HasMany
    {
        return $this->hasMany(StaffPayout::class, 'staff_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * The Teacher/Student whose referral code this user signed up with —
     * for a Teacher, who refers them for good. A student's referrer is
     * instead the StudentReferral in force (StudentReferralService).
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_id');
    }

    /**
     * The users who signed up with this user's referral code.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_id');
    }

    /**
     * The stretches of time this user (a student) was under a referrer.
     */
    public function studentReferrals(): HasMany
    {
        return $this->hasMany(StudentReferral::class, 'student_id');
    }

    /**
     * The students who are, or were, under this user's referral.
     */
    public function referredStudents(): HasMany
    {
        return $this->hasMany(StudentReferral::class, 'referrer_id');
    }

    /**
     * The latest rewards referring has earned this user, each with who paid
     * — what the earnings pages list.
     *
     * @return Collection<int, ReferralReward>
     */
    public function recentReferralRewards(int $limit = 20): Collection
    {
        return $this->referralRewards()->with('payment.user:id,name')->latest('id')->limit($limit)->get();
    }

    /**
     * The payments made while this user was the buyer's referrer.
     */
    public function referredPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'referrer_id');
    }

    /**
     * A Teacher's or Student's requests to take referral earnings out as cash.
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * What this user has earned by referring others.
     */
    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_id');
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasSuccessfulPayment(): bool
    {
        return $this->payments()->where('status', PaymentStatus::Success)->exists();
    }

    public function pendingPayment(): ?Payment
    {
        return $this->payments()->where('status', PaymentStatus::Pending)->latest('id')->first();
    }

    /**
     * A Bangladeshi mobile number in its one stored form (`01XXXXXXXXX`),
     * however it was typed — spaces, dashes, a leading +88 or 88. Null
     * when it isn't a valid mobile number.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = preg_replace('/^(?:88)(?=01)/', '', $digits);

        return preg_match('/^01[3-9]\d{8}$/', $digits) === 1 ? $digits : null;
    }

    public function activeSubscription(): ?Subscription
    {
        // The plan is what every caller goes on to read (its name, its
        // monthly limit), so it comes along in the same go.
        return $this->subscriptions()->active()->with('plan')->latest('starts_at')->first();
    }

    /**
     * Every subscription in force for this user right now — a user may hold
     * several (one subject here, another there), and their access is all
     * of them together. Includes a group plan someone else bought and
     * shares with this user's email, when it is meant for their role.
     *
     * @return Collection<int, Subscription>
     */
    public function activeSubscriptions(): Collection
    {
        return Subscription::query()
            ->active()
            ->where(fn ($query) => $query
                ->where('user_id', $this->id)
                ->orWhere(fn ($shared) => $shared
                    ->whereHas('members', fn ($members) => $members->current()->where('email', $this->email))
                    ->whereHas('plan', fn ($plan) => $plan->where('target_role', $this->role->subscriptionTargetRole()))))
            ->with(['plan', 'user:id,name'])
            ->latest('starts_at')
            ->get();
    }

    /**
     * The email with most of its name hidden ("ab***@gmail.com") — what a
     * referrer sees of the students under them.
     */
    public function maskedEmail(): string
    {
        [$name, $domain] = array_pad(explode('@', (string) $this->email, 2), 2, '');

        return mb_substr($name, 0, 2).'***'.($domain !== '' ? '@'.$domain : '');
    }

    /**
     * A student's class — what their purchases and practice default to.
     */
    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class);
    }
}

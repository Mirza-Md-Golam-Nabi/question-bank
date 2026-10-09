<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'google_id', 'avatar', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
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
     * The Teacher/Student whose referral code this user signed up with.
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
}

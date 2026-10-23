<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An offer or a piece of news an Admin writes (CLAUDE.md rule 13): shown on
 * the home page to everyone and in the dashboard banner of the roles it is
 * for — a student's only when their class is among the chosen ones, if any
 * are — while it is on and until each user closes it. The one place that
 * decides who an offer is for and whether it is on.
 */
#[Fillable([
    'title', 'body', 'image', 'button_label', 'button_url', 'target_roles', 'academic_class_ids',
    'starts_at', 'ends_at', 'is_active', 'created_by',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * The roles an offer can be for — everyone who signs in, but Admins.
     *
     * @var array<int, UserRole>
     */
    public const AUDIENCE_ROLES = [UserRole::Student, UserRole::Teacher, UserRole::School, UserRole::Staff];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'academic_class_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Kept as whole numbers, whatever the form sends — so a lookup by class
     * matches — and none at all for "every class".
     */
    protected function academicClassIds(): Attribute
    {
        return Attribute::make(set: fn (?array $ids): ?string => $ids ? json_encode(array_values(array_map('intval', $ids))) : null);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dismissals(): HasMany
    {
        return $this->hasMany(OfferDismissal::class);
    }

    /**
     * Switched on and within its dates — no job turns it on or off, the
     * dates are compared when it is shown.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /**
     * The live offers in a user's dashboard banner: for their role (and, for
     * a student, their class), not closed by them — newest first.
     */
    public function scopeInBannerOf(Builder $query, User $user): Builder
    {
        return $query->live()
            ->whereJsonContains('target_roles', $user->role->value)
            ->when($user->role === UserRole::Student, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNull('academic_class_ids')
                ->orWhereJsonLength('academic_class_ids', 0)
                ->when($user->academic_class_id, fn (Builder $query) => $query->orWhereJsonContains('academic_class_ids', (int) $user->academic_class_id))))
            ->whereDoesntHave('dismissals', fn (Builder $query) => $query->where('user_id', $user->id))
            ->latest('id');
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /**
     * Whether it may be sent to the bell and as a push notification now:
     * while it is on, and once only.
     */
    public function canBeSent(): bool
    {
        return $this->isLive() && $this->notified_at === null;
    }

    /**
     * Everyone it is for, who can sign in: active users of its roles — the
     * students only of its classes, when it names any.
     *
     * @return Builder<User>
     */
    public function audience(): Builder
    {
        $classIds = array_map('intval', $this->academic_class_ids ?? []);

        return User::query()
            ->where('status', UserStatus::Active)
            ->where(fn (Builder $query) => $query
                ->whereIn('role', array_diff($this->target_roles ?? [], [UserRole::Student->value]))
                ->when(in_array(UserRole::Student->value, $this->target_roles ?? [], true), fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('role', UserRole::Student)
                    ->when($classIds !== [], fn (Builder $query) => $query->whereIn('academic_class_id', $classIds)))));
    }

    /**
     * Who it is for, as the home page says it: each role, and the students'
     * classes when it names any.
     *
     * @return array<int, string>
     */
    public function audienceLabels(): array
    {
        $classNames = $this->academic_class_ids
            ? AcademicClass::query()->whereKey($this->academic_class_ids)->orderBy('order_index')->pluck('name')->implode(', ')
            : null;

        return collect($this->target_roles ?? [])
            ->map(fn (string $role): ?UserRole => UserRole::tryFrom($role))
            ->filter()
            ->map(fn (UserRole $role): string => $role === UserRole::Student && $classNames
                ? "{$role->getLabel()} ({$classNames})"
                : (string) $role->getLabel())
            ->values()
            ->all();
    }
}

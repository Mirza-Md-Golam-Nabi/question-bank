<?php

namespace App\Models;

use App\Enums\ReactivationRequestStatus;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A suspended Teacher's or Staff member's request to have their account
 * switched back on. Only a suspended user can ask, one request at a time;
 * a permanently suspended user can't reach their panel, so can't ask.
 * Approving is the one thing here that changes the user's status.
 */
#[Fillable(['user_id', 'message', 'status', 'admin_note', 'reviewed_by', 'reviewed_at'])]
class ReactivationRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ReactivationRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === ReactivationRequestStatus::Pending;
    }

    public static function canBeSubmittedBy(User $user): bool
    {
        return $user->isSuspended() && ! self::pendingFor($user);
    }

    public static function pendingFor(User $user): ?self
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('status', ReactivationRequestStatus::Pending)
            ->first();
    }

    public static function latestFor(User $user): ?self
    {
        return self::query()->where('user_id', $user->id)->latest('id')->first();
    }

    /**
     * @throws ValidationException When the user isn't suspended or already has a request waiting.
     */
    public static function submitFor(User $user, string $message): self
    {
        if (! self::canBeSubmittedBy($user)) {
            throw ValidationException::withMessages([
                'message' => __('You cannot send a reactivation request right now.'),
            ]);
        }

        return self::create(['user_id' => $user->id, 'message' => $message]);
    }

    /**
     * Switches the account back on. False when the request was already
     * handled.
     */
    public function approve(User $admin, ?string $note = null): bool
    {
        return $this->review(ReactivationRequestStatus::Approved, $admin, $note, function (): void {
            // Only lifts an ordinary suspension — never a permanent one
            // placed after the request was sent.
            if ($this->user->isSuspended()) {
                $this->user->update(['status' => UserStatus::Active]);
            }
        });
    }

    public function decline(User $admin, ?string $note = null): bool
    {
        return $this->review(ReactivationRequestStatus::Declined, $admin, $note);
    }

    private function review(ReactivationRequestStatus $outcome, User $admin, ?string $note, ?callable $then = null): bool
    {
        return DB::transaction(function () use ($outcome, $admin, $note, $then): bool {
            $request = self::whereKey($this->id)->lockForUpdate()->first();

            if (! $request?->isPending()) {
                return false;
            }

            $this->update([
                'status' => $outcome,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $then && $then();

            Log::info('Reactivation request reviewed.', [
                'request_id' => $this->id,
                'user_id' => $this->user_id,
                'outcome' => $outcome->value,
                'reviewed_by' => $admin->id,
            ]);

            return true;
        });
    }
}

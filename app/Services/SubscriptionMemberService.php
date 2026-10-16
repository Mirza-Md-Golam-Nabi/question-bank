<?php

namespace App\Services;

use App\Models\BillingSetting;
use App\Models\Subscription;
use App\Models\SubscriptionMember;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Who shares a group plan — the only place the rules for adding and
 * removing people live:
 *
 *  - the buyer adds the others by their Google email, up to the plan's
 *    seats (the buyer takes one); each then signs in with their own
 *    account, so it is a shared bill, not a shared login;
 *  - only someone of the role the plan is for (an email with no account
 *    yet can be added; it only counts once a matching account signs in);
 *  - someone can be taken off only once in the Admin's number of days
 *    (BillingSetting::group_member_change_days), so a few seats can't be
 *    passed round a whole class;
 *  - when the buyer's subscription ends, everyone's access ends with it
 *    (User::activeSubscriptions() only finds subscriptions in force).
 */
class SubscriptionMemberService
{
    /**
     * Whether the user bought this subscription and it has seats to share.
     */
    public function canManage(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->id && $subscription->seats() > 1;
    }

    /**
     * @return Collection<int, SubscriptionMember>
     */
    public function membersOf(Subscription $subscription): Collection
    {
        return $subscription->members()->current()->with('user:id,name,email')->orderBy('id')->get();
    }

    /**
     * Seats not taken yet, the buyer's own not counted.
     */
    public function freeSeats(Subscription $subscription): int
    {
        return max(0, $subscription->seats() - 1 - $subscription->members()->current()->count());
    }

    /**
     * When someone can next be taken off the plan; null when that is now.
     */
    public function nextRemovalAllowedAt(Subscription $subscription): ?CarbonInterface
    {
        $lastRemoval = $subscription->members()->whereNotNull('removed_at')->max('removed_at');

        if (! $lastRemoval) {
            return null;
        }

        $allowedAt = now()->parse($lastRemoval)->addDays(BillingSetting::current()->group_member_change_days);

        return $allowedAt->isFuture() ? $allowedAt : null;
    }

    /**
     * @throws ValidationException
     */
    public function add(User $owner, Subscription $subscription, ?string $email): SubscriptionMember
    {
        $email = strtolower(trim((string) $email));

        if (! $this->canManage($owner, $subscription)) {
            throw ValidationException::withMessages(['email' => __('This plan is not yours to share.')]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => __('Enter the Gmail address they sign in with.')]);
        }

        if ($email === strtolower($owner->email)) {
            throw ValidationException::withMessages(['email' => __('You already have this plan.')]);
        }

        $account = User::where('email', $email)->first();

        if ($account && $account->role->subscriptionTargetRole() !== $subscription->plan?->target_role) {
            throw ValidationException::withMessages(['email' => __('This account is not one this plan is meant for.')]);
        }

        return DB::transaction(function () use ($owner, $subscription, $email): SubscriptionMember {
            // Two adds at once can't both take the last seat.
            Subscription::whereKey($subscription->id)->lockForUpdate()->value('id');

            if ($subscription->members()->current()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => __('This person is already on the plan.')]);
            }

            if ($this->freeSeats($subscription) === 0) {
                throw ValidationException::withMessages(['email' => __('Every seat on this plan is taken.')]);
            }

            $member = $subscription->members()->create(['email' => $email]);

            Log::info('Group plan member added.', [
                'subscription_id' => $subscription->id,
                'owner_id' => $owner->id,
                'member_id' => $member->id,
            ]);

            return $member;
        });
    }

    /**
     * @throws ValidationException
     */
    public function remove(User $owner, SubscriptionMember $member): void
    {
        $subscription = $member->subscription;

        if (! $this->canManage($owner, $subscription) || $member->removed_at !== null) {
            throw ValidationException::withMessages(['member' => __('This person is not on your plan.')]);
        }

        DB::transaction(function () use ($owner, $subscription, $member): void {
            Subscription::whereKey($subscription->id)->lockForUpdate()->value('id');

            if ($allowedAt = $this->nextRemovalAllowedAt($subscription)) {
                throw ValidationException::withMessages([
                    'member' => __('You can take someone off the plan again on :date.', ['date' => $allowedAt->format('M j, Y')]),
                ]);
            }

            $member->update(['removed_at' => now()]);

            Log::info('Group plan member removed.', [
                'subscription_id' => $subscription->id,
                'owner_id' => $owner->id,
                'member_id' => $member->id,
            ]);
        });
    }
}

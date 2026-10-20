<?php

namespace App\Services;

use App\Enums\AdminNotificationTopic;
use App\Models\BillingSetting;
use App\Models\User;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Support\Traits\Localizable;

/**
 * The one way something lands in a user's bell (Filament's database
 * notifications, every panel). What to say is the caller's; this decides:
 *
 *  - who: one user, or the Admins chosen for that kind of notification
 *    (BillingSetting::adminsToNotify());
 *  - in what language: each notification is written in its recipient's
 *    panel language (UserRole::defaultLocale()), as it is stored as text;
 *  - when: at once — not through the queue, which needs a worker running
 *    for anything to arrive.
 */
final class BellNotifier
{
    use Localizable;

    /**
     * @param  Closure(User): Notification  $notification  Writes it — called in the recipient's language.
     */
    public function toUser(User $user, Closure $notification): void
    {
        $this->withLocale($user->role->defaultLocale()->value, fn () => $user->notifyNow($notification($user)->toDatabase()));
    }

    /**
     * @param  Closure(User): Notification  $notification  Writes it — called once per Admin, in their language.
     */
    public function toAdmins(AdminNotificationTopic $topic, Closure $notification): void
    {
        foreach (BillingSetting::current()->adminsToNotify($topic) as $admin) {
            $this->toUser($admin, $notification);
        }
    }
}

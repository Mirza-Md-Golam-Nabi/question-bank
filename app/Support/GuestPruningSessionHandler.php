<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * The session lifetime is how long someone stays signed in, which can be
 * years — too long to keep the row of every visitor who never signed in.
 * Those still go after `session.guest_lifetime` minutes.
 */
class GuestPruningSessionHandler extends DatabaseSessionHandler
{
    public function gc($lifetime): int
    {
        $guestsLastActiveBefore = $this->currentTime() - (config('session.guest_lifetime') * 60);

        return parent::gc($lifetime)
            + $this->getQuery()->whereNull('user_id')->where('last_activity', '<=', $guestsLastActiveBefore)->delete();
    }
}

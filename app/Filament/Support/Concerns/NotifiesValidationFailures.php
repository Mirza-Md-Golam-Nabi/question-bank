<?php

namespace App\Filament\Support\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * For a page that hands a request to a service which can refuse it (a plan
 * not on sale, no seat left, too little to withdraw…): the refusal is shown
 * as a red notification rather than a form error. The one place that
 * try/catch-and-notify is written.
 */
trait NotifiesValidationFailures
{
    /**
     * Runs `$work` and returns what it returned — or, when the service
     * refused, notifies and returns null (halting `$action` first, if one is
     * given, so its modal stays open). A `$work` with nothing to return
     * should return true.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $work
     * @return TResult|null
     */
    protected function rescueWithNotification(Closure $work, ?Action $action = null): mixed
    {
        try {
            return $work();
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $action?->halt();

            return null;
        }
    }
}

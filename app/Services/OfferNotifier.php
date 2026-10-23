<?php

namespace App\Services;

use App\Models\Offer;
use App\Models\User;
use App\Notifications\OfferPushNotification;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifications;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * Sends an offer to its audience (Offer::audience()) — to the bell, as a
 * push notification, or both, as the Admin chooses — while it is on, and
 * only once. The banner and the home page need nothing sent; this is the
 * extra nudge. The sending itself runs after the Admin's page has answered
 * (`defer()`), a few hundred users at a time, so a big audience never keeps
 * the Admin waiting and needs no queue worker.
 */
class OfferNotifier
{
    /**
     * How many users are read and notified at a time — a technical limit
     * on memory.
     */
    public const CHUNK = 500;

    public function __construct(private BellNotifier $bell) {}

    /**
     * @throws ValidationException when it isn't on, was sent already, or neither way is chosen
     */
    public function send(Offer $offer, User $admin, bool $toBell, bool $toPush): void
    {
        if (! $toBell && ! $toPush) {
            throw ValidationException::withMessages(['offer' => __('Choose the bell, push notifications or both.')]);
        }

        if (! $offer->isLive()) {
            throw ValidationException::withMessages(['offer' => __('Only an offer that is on can be sent.')]);
        }

        // Claimed in one statement, so two Admins pressing at once can't
        // both send it.
        $claimed = Offer::query()->whereKey($offer->id)->whereNull('notified_at')
            ->update(['notified_at' => now(), 'notified_by' => $admin->id]);

        if ($claimed === 0) {
            throw ValidationException::withMessages(['offer' => __('This offer has been sent already.')]);
        }

        Log::info('Offer sent', ['offer_id' => $offer->id, 'by' => $admin->id, 'bell' => $toBell, 'push' => $toPush]);

        defer(fn () => $this->deliver($offer->fresh(), $toBell, $toPush));
    }

    /**
     * Who it would reach now: everyone it is for, and those of them who
     * have push notifications on.
     *
     * @return array{users: int, push: int}
     */
    public function reach(Offer $offer): array
    {
        return [
            'users' => $offer->audience()->count(),
            'push' => $offer->audience()->whereHas('pushSubscriptions')->count(),
        ];
    }

    private function deliver(Offer $offer, bool $toBell, bool $toPush): void
    {
        set_time_limit(0);

        $offer->audience()
            ->when(! $toBell, fn (Builder $query) => $query->whereHas('pushSubscriptions'))
            ->when($toPush, fn (Builder $query) => $query->with('pushSubscriptions'))
            ->chunkById(self::CHUNK, function (Collection $users) use ($offer, $toBell, $toPush): void {
                if ($toBell) {
                    $users->each(fn (User $user) => $this->bell->toUser($user, fn (User $user): Notification => $this->bellNotification($offer, $user)));
                }

                if ($toPush) {
                    Notifications::sendNow($users->filter(fn (User $user): bool => $user->pushSubscriptions->isNotEmpty()), new OfferPushNotification($offer));
                }
            });
    }

    private function bellNotification(Offer $offer, User $user): Notification
    {
        return Notification::make()
            ->title($offer->title)
            ->body(Str::limit($offer->body, 200))
            ->icon('heroicon-o-gift')
            ->iconColor('primary')
            ->actions([
                Action::make('open')
                    ->label($offer->button_label ?: __('See the offer'))
                    ->url($offer->button_url ?: route('filament.'.$user->role->panelId().'.pages.dashboard', absolute: false))
                    ->markAsRead(),
            ]);
    }
}

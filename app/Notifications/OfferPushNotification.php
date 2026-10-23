<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * An offer on the phone's notification bar (public/sw.js shows it), sent
 * once by OfferNotifier to the users of its audience who switched push on.
 * Tapping it opens the offer's link, or the app.
 */
class OfferPushNotification extends Notification
{
    /**
     * How long the push service keeps trying a phone that is off, in
     * seconds — a technical limit.
     */
    public const KEEP_TRYING_SECONDS = 86400;

    public function __construct(public Offer $offer) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->offer->title)
            ->body(Str::limit($this->offer->body, 160))
            ->icon((string) config('pwa.icons.192'))
            ->data(['url' => $this->offer->button_url ?: route('pwa.start')])
            ->options(['TTL' => self::KEEP_TRYING_SECONDS]);
    }
}

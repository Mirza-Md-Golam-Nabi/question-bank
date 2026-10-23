{{-- "Switch on notifications": asks the browser only when the user presses
     the button (a phone won't ask otherwise), then registers this device
     (PushSubscriptionController). Not shown where push can't work, once it
     is on or blocked, or for a week after "Later". Once on, every visit
     quietly keeps the device registered. --}}
@if (config('webpush.vapid.public_key'))
    <div
        x-data="{
            visible: false,
            key: @js(config('webpush.vapid.public_key')),
            laterKey: 'qb-push-later',
            supported() {
                return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            },
            init() {
                if (! this.supported()) return;
                if (Notification.permission === 'granted') return this.register();
                let later = 0;
                try { later = Number(localStorage.getItem(this.laterKey) || 0); } catch (e) {}
                this.visible = Notification.permission === 'default' && Date.now() - later > 7 * 24 * 3600 * 1000;
            },
            async enable() {
                this.visible = false;
                if (await Notification.requestPermission() === 'granted') await this.register();
            },
            later() {
                this.visible = false;
                try { localStorage.setItem(this.laterKey, String(Date.now())); } catch (e) {}
            },
            async register() {
                const registration = await navigator.serviceWorker.register('/sw.js');
                const subscription = await registration.pushManager.getSubscription()
                    || await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.keyBytes() });
                await fetch(@js(route('push-subscriptions.store')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
                    body: JSON.stringify({ ...subscription.toJSON(), contentEncoding: (PushManager.supportedContentEncodings || ['aes128gcm'])[0] }),
                });
            },
            keyBytes() {
                const base64 = (this.key + '='.repeat((4 - this.key.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
                return Uint8Array.from(atob(base64), (character) => character.charCodeAt(0));
            },
        }"
        x-show="visible"
        x-cloak
        class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:flex-row sm:items-center dark:bg-gray-900 dark:ring-white/10"
    >
        <p class="flex-1 text-sm text-gray-700 dark:text-gray-200">{{ __('Get offers and news on your phone — switch on notifications.') }}</p>
        <div class="flex shrink-0 gap-2">
            <x-filament::button size="sm" x-on:click="enable()">{{ __('Switch on') }}</x-filament::button>
            <x-filament::button size="sm" color="gray" x-on:click="later()">{{ __('Later') }}</x-filament::button>
        </div>
    </div>
@endif

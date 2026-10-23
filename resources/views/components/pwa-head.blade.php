{{-- The installable app (config/pwa.php): its manifest, colour and icon, and
     the service worker that shows push notifications. In the <head> of every
     page the app can open — the panels (PanelDefaults), the home page, /app. --}}
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ config('pwa.theme_color') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ config('pwa.short_name') }}">
<link rel="apple-touch-icon" href="{{ config('pwa.icons.192') }}">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
    }
</script>

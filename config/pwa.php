<?php

/*
|--------------------------------------------------------------------------
| The installable app (PWA)
|--------------------------------------------------------------------------
|
| What a phone shows for the app once installed from the browser: its name,
| colours and icons. One app for everyone — it opens on /app, which sends a
| signed-in user to their own panel and anyone else to "who are you?".
| Served as /manifest.webmanifest (PwaController).
|
*/

return [

    'name' => 'Next Fusion',

    // Under the icon on the home screen — 12 characters or fewer show whole.
    'short_name' => 'Next Fusion',

    'description' => 'প্রশ্নব্যাংক, পরীক্ষা ও অনুশীলন',

    'theme_color' => '#4f46e5',

    // The logo's own black, so the splash screen and the icon are one piece.
    'background_color' => '#060606',

    /*
    | লোগো (PNG, ঠিক এই মাপের)। লোগো বদলালে public/images/pwa-এর ফাইল
    | একই নামে বদলে দিন। Chrome icon ছাড়া "Install" দেখায় না. `maskable` is
    | the same logo with extra space around it, so a phone can crop it to a
    | circle or a square.
    */
    'icons' => [
        '192' => '/images/pwa/icon-192.png',
        '512' => '/images/pwa/icon-512.png',
        'maskable' => '/images/pwa/icon-512-maskable.png',
    ],

];

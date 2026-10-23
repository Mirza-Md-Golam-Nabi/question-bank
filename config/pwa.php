<?php

/*
|--------------------------------------------------------------------------
| The installable app (PWA)
|--------------------------------------------------------------------------
|
| What a phone shows for the app once installed from the browser: its name,
| colours and icons. One app for everyone — it opens on /app, which sends a
| signed-in user to their own panel and anyone else to "who are you?".
| Served as /manifest.webmanifest (PwaManifestController).
|
*/

return [

    'name' => 'Next Fusion',

    // Under the icon on the home screen — 12 characters or fewer show whole.
    'short_name' => 'Next Fusion',

    'description' => 'প্রশ্নব্যাংক, পরীক্ষা ও অনুশীলন',

    'theme_color' => '#4f46e5',

    'background_color' => '#020617',

    /*
    | এখানে লোগোর লিংক বসান (PNG; পুরো লিংক বা /images/... এর মতো পথ)।
    | Put the logo's links here. `maskable` is the same logo with extra
    | space around it, so a phone can crop it to a circle or a square.
    */
    'icons' => [
        '192' => 'LOGO-LINK-192x192-HERE',
        '512' => 'LOGO-LINK-512x512-HERE',
        'maskable' => 'LOGO-LINK-512x512-MASKABLE-HERE',
    ],

];

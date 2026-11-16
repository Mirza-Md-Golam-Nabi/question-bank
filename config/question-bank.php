<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin Seeder
    |--------------------------------------------------------------------------
    |
    | Used once by SuperAdminSeeder to create the very first Super Admin
    | account. Subsequent admins are created from inside the Admin Panel.
    |
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bringing questions from the live server to the test server
    |--------------------------------------------------------------------------
    |
    | What this server is (App\Support\QuestionSync): "source" on the live
    | server — it only shares, and only while its Super Admin has the
    | sharing open; "consumer" on the test server — it only fetches. Unset:
    | neither. Both servers hold the same secret; the test server also the
    | live server's address.
    |
    */

    'question_sync' => [
        'role' => env('QUESTION_SYNC_ROLE'),
        'secret' => env('QUESTION_SYNC_SECRET'),
        'source_url' => env('QUESTION_SYNC_SOURCE_URL'),
    ],

];

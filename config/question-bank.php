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

];

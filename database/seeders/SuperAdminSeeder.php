<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::firstOrCreate(
            ['email' => config('question-bank.super_admin.email')],
            [
                'name' => config('question-bank.super_admin.name'),
                'password' => config('question-bank.super_admin.password'),
                'role' => UserRole::SuperAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );

        if (! $superAdmin->hasRole(UserRole::SuperAdmin->value)) {
            $superAdmin->assignRole(UserRole::SuperAdmin->value);
        }
    }
}

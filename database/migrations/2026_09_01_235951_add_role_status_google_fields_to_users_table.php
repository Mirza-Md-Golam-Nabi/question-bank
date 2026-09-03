<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
            $table->string('google_id')->unique()->nullable()->after('password');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('role')->default(UserRole::Student->value)->after('avatar');
            $table->string('status')->default(UserStatus::Active->value)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar', 'role', 'status']);
            $table->string('password')->nullable(false)->change();
        });
    }
};

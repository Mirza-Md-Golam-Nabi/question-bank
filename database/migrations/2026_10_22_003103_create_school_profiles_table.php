<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // What the school tells the Admin about itself before approval.
            $table->string('name')->nullable();
            $table->string('eiin')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            // The school management software's half of the connection: the
            // code it identifies itself with, the secret both sides sign
            // their requests with (never sent over the wire), and where its
            // installation answers.
            $table->string('connection_code')->unique();
            $table->text('connection_secret');
            $table->string('installation_url')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();
        });

        // The new role, for databases seeded before it existed (RoleSeeder
        // makes it on a fresh one).
        Role::firstOrCreate(['name' => UserRole::School->value, 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Schema::dropIfExists('school_profiles');
    }
};

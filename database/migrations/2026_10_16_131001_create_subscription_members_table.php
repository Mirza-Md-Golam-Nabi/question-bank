<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group plans (a few students together, a coaching centre's teachers): the
 * buyer adds the others by their Google email, and each signs in with their
 * own account. A member is never deleted, only marked removed, so how often
 * the buyer swaps people can be counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->dateTime('removed_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'removed_at']);
            $table->index(['subscription_id', 'removed_at']);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('group_member_change_days')->default(30)->after('student_min_withdrawal');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('group_member_change_days');
        });

        Schema::dropIfExists('subscription_members');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('referral_enabled')->default(false);
            $table->decimal('referrer_reward_percent', 5, 2)->default(0);
            $table->decimal('referee_discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('credit_expiry_months')->nullable();
            $table->unsignedSmallInteger('phone_bonus_exams')->default(0);
            $table->boolean('otp_required_for_credit')->default(false);
            $table->string('bkash_number', 20)->nullable();
            $table->string('nagad_number', 20)->nullable();
            $table->string('rocket_number', 20)->nullable();
            $table->text('payment_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};

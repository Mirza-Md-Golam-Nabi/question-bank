<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            // Signed: credits are positive, debits negative.
            $table->decimal('amount', 10, 2);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};

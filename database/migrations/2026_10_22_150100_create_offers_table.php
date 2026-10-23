<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offers and news an Admin writes — on the home page and in the panels'
     * dashboard banner, to the roles (and students' classes) chosen; sent to
     * the bell and as a push notification only when the Admin says so.
     */
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('image')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url', 500)->nullable();
            $table->json('target_roles');
            $table->json('academic_class_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::create('offer_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['offer_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_dismissals');
        Schema::dropIfExists('offers');
    }
};

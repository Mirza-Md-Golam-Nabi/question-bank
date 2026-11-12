<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The school/coaching names a Teacher or School prints at the top of a
 * question paper. The first one (`is_free`) is always usable; every other
 * one only while it is paid for (`paid_until`). `renamed_at` is when the
 * name was last changed — a name can be changed again only after the
 * Admin's number of days, so the free one can't stand in for many.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->boolean('is_free')->default(false);
            $table->dateTime('paid_until')->nullable();
            $table->dateTime('renamed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_free']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institution_names');
    }
};

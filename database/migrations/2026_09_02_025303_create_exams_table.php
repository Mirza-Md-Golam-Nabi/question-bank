<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('exam_type');
            $table->string('generation_mode')->nullable();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_minutes');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->string('status')->default('draft');
            $table->string('share_token')->nullable()->unique();
            $table->dateTime('link_expires_at')->nullable();
            $table->boolean('is_link_active')->default(true);
            $table->timestamps();

            $table->index(['created_by', 'exam_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

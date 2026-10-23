<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every time a School sent an exam's MCQ marks to its school
        // management software's results: where to, how, and what happened.
        Schema::create('school_result_pushes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('school_class_id');
            $table->string('school_class_name')->nullable();
            $table->unsignedBigInteger('school_exam_id');
            $table->string('school_exam_name')->nullable();
            $table->unsignedBigInteger('school_subject_id');
            $table->string('school_subject_name')->nullable();
            $table->decimal('school_full_marks', 8, 2);
            $table->boolean('in_proportion');
            $table->boolean('overwrite');
            $table->unsignedInteger('sent_count');
            $table->unsignedInteger('saved_count');
            // What the school's software left as it was, and why.
            $table->json('skipped')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_result_pushes');
    }
};

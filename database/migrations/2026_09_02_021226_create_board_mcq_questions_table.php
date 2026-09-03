<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_mcq_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_question_paper_id')->constrained()->cascadeOnDelete();
            $table->longText('question_text');
            $table->string('question_image')->nullable();
            $table->json('options');
            $table->string('correct_answer');
            $table->decimal('marks', 6, 2)->default(1);
            $table->unsignedInteger('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_mcq_questions');
    }
};

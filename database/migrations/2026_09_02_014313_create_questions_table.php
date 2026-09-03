<?php

use App\Enums\QuestionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('question_type');
            $table->longText('question_text');
            $table->string('question_image')->nullable();
            $table->json('options')->nullable();
            $table->text('correct_answer')->nullable();
            $table->decimal('marks', 6, 2)->default(1);
            $table->string('difficulty');
            $table->string('status')->default(QuestionStatus::Pending->value);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_latest')->default(true);
            $table->timestamps();

            $table->index(['status', 'is_latest']);
            $table->index(['chapter_id', 'status', 'is_latest']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

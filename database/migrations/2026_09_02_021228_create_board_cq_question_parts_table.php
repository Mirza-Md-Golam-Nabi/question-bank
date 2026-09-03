<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_cq_question_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_cq_question_id')->constrained()->cascadeOnDelete();
            $table->string('part_type');
            $table->unsignedTinyInteger('part_order');
            $table->longText('part_text');
            $table->string('part_image')->nullable();
            $table->decimal('marks', 6, 2);
            $table->timestamps();

            $table->unique(['board_cq_question_id', 'part_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('board_cq_question_parts');
    }
};

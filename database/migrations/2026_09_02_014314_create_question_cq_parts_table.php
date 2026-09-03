<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_cq_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('part_type');
            $table->unsignedTinyInteger('part_order');
            $table->longText('part_text');
            $table->string('part_image')->nullable();
            $table->decimal('marks', 6, 2);
            $table->timestamps();

            $table->unique(['question_id', 'part_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_cq_parts');
    }
};

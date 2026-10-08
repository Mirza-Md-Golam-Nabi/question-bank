<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Existing exams were all created for the share-link flow.
            $table->string('delivery_mode')->default('online')->after('generation_mode');
            $table->foreignId('class_subject_id')->nullable()->after('subject_id')->constrained('class_subjects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_subject_id');
            $table->dropColumn('delivery_mode');
        });
    }
};

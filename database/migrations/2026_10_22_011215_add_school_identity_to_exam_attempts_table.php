<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            // Who sat a School's exam, as its entry form took them — the ids
            // are the school management software's own, the names a copy for
            // showing. The student's name is in guest_name.
            $table->unsignedBigInteger('school_class_id')->nullable()->after('guest_contact');
            $table->string('school_class_name')->nullable()->after('school_class_id');
            $table->unsignedBigInteger('school_section_id')->nullable()->after('school_class_name');
            $table->string('school_section_name')->nullable()->after('school_section_id');
            $table->string('school_roll', 20)->nullable()->after('school_section_name');

            $table->index(['exam_id', 'school_class_id', 'school_roll']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropIndex(['exam_id', 'school_class_id', 'school_roll']);
            $table->dropColumn(['school_class_id', 'school_class_name', 'school_section_id', 'school_section_name', 'school_roll']);
        });
    }
};

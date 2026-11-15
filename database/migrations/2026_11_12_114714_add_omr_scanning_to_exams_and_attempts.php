<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // A print-only paper's MCQ marks — what its scanned OMR sheets are
            // scored out of, its total_marks also counting the CQ. Null when
            // the two are the same (every exam also taken online).
            $table->decimal('mcq_marks', 8, 2)->nullable()->after('total_marks');
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            // A paper answered on a printed OMR sheet and scanned in by the
            // exam's teacher; who sat it is its roll (school_roll) and an
            // optional name (guest_name).
            $table->boolean('is_omr')->default(false)->after('is_guest');
        });
    }

    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('is_omr');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('mcq_marks');
        });
    }
};

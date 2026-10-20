<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How big the images given with a JSON import may be: each image, and all
 * of them together (a ZIP counts by what is inside it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedInteger('question_import_image_max_kb')->default(1024)->after('question_import_max');
            $table->unsignedSmallInteger('question_import_images_max_mb')->default(20)->after('question_import_image_max_kb');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['question_import_image_max_kb', 'question_import_images_max_mb']);
        });
    }
};

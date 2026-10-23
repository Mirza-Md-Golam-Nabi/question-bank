<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How-to guides an Admin writes for the panels' "Help" page and the
     * "How do I…?" button of a page (CLAUDE.md rule 14).
     */
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            // The seeded drafts' own key, so seeding again never doubles them.
            $table->string('slug')->nullable()->unique();
            $table->string('category');
            $table->string('title_bn');
            $table->string('title_en')->nullable();
            $table->longText('body_bn');
            $table->longText('body_en')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->json('target_roles');
            $table->json('page_keys')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};

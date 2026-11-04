<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A subscription the Super Admin gave away for nothing: it works like any
 * other, but doesn't make its holder a paying customer (no commission of
 * their own from it, and no refund).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('is_gift')->default(false)->after('selections');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('is_gift');
        });
    }
};

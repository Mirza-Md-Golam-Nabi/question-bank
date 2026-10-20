<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which Admins get the bell notifications of each kind (AdminNotificationTopic):
 * `{"purchases": [3, 7]}`. A kind with nobody chosen goes to every Admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->json('admin_notification_recipients')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('admin_notification_recipients');
        });
    }
};

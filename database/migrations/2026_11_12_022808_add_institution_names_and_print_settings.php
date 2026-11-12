<?php

use App\Models\BillingSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paying for an extra institution name, and the print settings.
 *
 * A payment can now be for an institution name (`type`): which name
 * (`institution_name_id`) and how many days it adds (`period_days`, the
 * Admin's number when it was asked for). The Admin sets the price, the
 * days a payment adds, how often a name may be changed, and the default
 * instructions printed above the MCQ and CQ parts of a paper. A user's own
 * print settings (columns, paper size, fonts…) are kept on the user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('institution_name_id')->nullable()->after('previous_plan_id')->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('period_days')->nullable()->after('institution_name_id');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('institution_name_price', 10, 2)->default(0)->after('plan_downgrade_fee_percent');
            $table->unsignedSmallInteger('institution_name_days')->default(30)->after('institution_name_price');
            $table->unsignedSmallInteger('institution_name_change_days')->default(30)->after('institution_name_days');
            $table->text('print_mcq_instructions')->nullable();
            $table->text('print_cq_instructions')->nullable();
        });

        // The saved settings start with the same instructions as unsaved ones.
        $defaults = new BillingSetting;

        DB::table('billing_settings')->update([
            'print_mcq_instructions' => $defaults->print_mcq_instructions,
            'print_cq_instructions' => $defaults->print_cq_instructions,
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->json('print_settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('print_settings');
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['institution_name_price', 'institution_name_days', 'institution_name_change_days', 'print_mcq_instructions', 'print_cq_instructions']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_name_id');
            $table->dropColumn('period_days');
        });
    }
};

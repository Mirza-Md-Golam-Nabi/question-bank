<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the filters, sorts and groupings the application actually
 * runs, found by auditing every query against the existing indexes. Each
 * one is named for the query it serves.
 */
return new class extends Migration
{
    /**
     * Table => list of index column sets.
     *
     * @var array<string, array<int, array<int, string>>>
     */
    private array $indexes = [
        'users' => [
            // Admin's Teachers / Staffs lists, payouts and the referral
            // report all start from "users of this role".
            ['role', 'status'],
        ],
        'questions' => [
            // "My questions" counts by status (dashboards, breakdowns) and
            // the recent-questions list, both for one author's latest versions.
            ['created_by', 'is_latest', 'status'],
            ['created_by', 'is_latest', 'created_at'],
            // The picker: a chapter's approved questions of one type.
            ['chapter_id', 'status', 'is_latest', 'question_type'],
        ],
        'exams' => [
            // Monthly limit: one creator's exams of a type inside a month,
            // and the teacher's exam list newest first.
            ['created_by', 'exam_type', 'created_at'],
        ],
        'exam_attempts' => [
            // "Has this student sat this exam" / resume, and the result sheet.
            ['exam_id', 'student_id'],
            ['exam_id', 'status'],
            // A student's own results, newest first.
            ['student_id', 'status', 'submitted_at'],
        ],
        'attempt_answers' => [
            // Per-question tallies of the teacher's question analysis.
            ['question_id', 'attempt_id', 'is_correct'],
        ],
        'staff_earnings' => [
            // A staff member's unpaid earnings, and their earnings by month.
            ['staff_id', 'status'],
            ['staff_id', 'created_at'],
        ],
        'staff_payouts' => [
            ['staff_id', 'paid_at'],
        ],
        'payments' => [
            // The Admin's payment list: newest first, usually filtered by status.
            ['status', 'created_at'],
        ],
        'question_rates' => [
            // "The rate in effect for this subject today".
            ['subject_id', 'effective_from'],
        ],
        'chapters' => [
            ['class_subject_id', 'order_index'],
        ],
        'topics' => [
            ['chapter_id', 'order_index'],
        ],
        'class_subjects' => [
            ['academic_class_id', 'order_index'],
        ],
        'subscription_plans' => [
            ['target_role', 'price'],
        ],
        'reactivation_requests' => [
            ['status', 'created_at'],
        ],
        'wallet_transactions' => [
            ['user_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $columnSets) {
            Schema::table($tableName, function (Blueprint $table) use ($columnSets): void {
                foreach ($columnSets as $columns) {
                    $table->index($columns);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $columnSets) {
            Schema::table($tableName, function (Blueprint $table) use ($columnSets): void {
                foreach ($columnSets as $columns) {
                    $table->dropIndex($columns);
                }
            });
        }
    }
};

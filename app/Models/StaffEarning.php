<?php

namespace App\Models;

use App\Enums\StaffEarningStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['staff_id', 'question_id', 'amount', 'status', 'payout_id'])]
class StaffEarning extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => StaffEarningStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * A staff member's earnings added up — in the database, in one query,
     * rather than by loading every earning row (there is one per approved
     * question, so a busy staff member has thousands).
     *
     * @return array{count: int, earned: float, paid: float, unpaid: float, this_month: float}
     */
    public static function totalsFor(int $staffId): array
    {
        $totals = self::query()
            ->where('staff_id', $staffId)
            ->selectRaw('count(*) as earnings_count')
            ->selectRaw('coalesce(sum(amount), 0) as earned')
            ->selectRaw('coalesce(sum(case when status = ? then amount else 0 end), 0) as paid', [StaffEarningStatus::Paid->value])
            ->selectRaw('coalesce(sum(case when created_at between ? and ? then amount else 0 end), 0) as this_month', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->toBase()
            ->first();

        return [
            'count' => (int) $totals->earnings_count,
            'earned' => (float) $totals->earned,
            'paid' => (float) $totals->paid,
            'unpaid' => round((float) $totals->earned - (float) $totals->paid, 2),
            'this_month' => (float) $totals->this_month,
        ];
    }

    /**
     * What a staff member earned in each of the last `$months` months,
     * this month first — every month present, zero when nothing was
     * earned. Summed per month by the database over just that period.
     *
     * @return Collection<int, array{label: string, total: float}>
     */
    public static function monthlyTotalsFor(int $staffId, int $months): Collection
    {
        // The one expression here that differs between SQLite (tests) and
        // MySQL: "the YYYY-MM a timestamp falls in".
        $monthOf = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "date_format(created_at, '%Y-%m')";

        $totals = self::query()
            ->where('staff_id', $staffId)
            ->where('created_at', '>=', now()->subMonthsNoOverflow($months - 1)->startOfMonth())
            ->selectRaw("{$monthOf} as month, sum(amount) as total")
            ->groupBy('month')
            ->toBase()
            ->pluck('total', 'month');

        return collect(range(0, $months - 1))->map(function (int $monthsAgo) use ($totals): array {
            $month = now()->subMonthsNoOverflow($monthsAgo);

            return [
                'label' => $month->format('M \'y'),
                'total' => (float) ($totals[$month->format('Y-m')] ?? 0),
            ];
        });
    }

    /**
     * A staff member's earnings per class + subject, counted and summed
     * by the database.
     *
     * @return Collection<int, array{class: string, subject: string, count: int, total: float}>
     */
    public static function byClassSubjectFor(int $staffId): Collection
    {
        $rows = self::query()
            ->where('staff_earnings.staff_id', $staffId)
            ->join('questions', 'questions.id', '=', 'staff_earnings.question_id')
            ->join('chapters', 'chapters.id', '=', 'questions.chapter_id')
            ->groupBy('chapters.class_subject_id')
            ->selectRaw('chapters.class_subject_id, count(*) as earnings_count, sum(staff_earnings.amount) as total')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->class_subject_id => ['count' => (int) $row->earnings_count, 'total' => (float) $row->total],
            ]);

        return ClassSubject::describe($rows);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(StaffPayout::class);
    }
}

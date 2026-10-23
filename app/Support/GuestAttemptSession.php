<?php

namespace App\Support;

use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Which attempts without an account (a guest's, a School student's) this
 * browser started — the only ones it may open. Their ids are guessable, so
 * an id alone is never enough to open someone else's exam.
 */
class GuestAttemptSession
{
    private const SESSION_KEY = 'guest_exam_attempts';

    public static function remember(Request $request, ExamAttempt $attempt): void
    {
        $request->session()->put(
            self::SESSION_KEY,
            collect($request->session()->get(self::SESSION_KEY, []))->push($attempt->id)->unique()->values()->all(),
        );
    }

    /**
     * @return Builder<ExamAttempt>
     */
    public static function attempts(Request $request): Builder
    {
        return ExamAttempt::query()
            ->where('is_guest', true)
            ->whereIn('id', (array) $request->session()->get(self::SESSION_KEY, []));
    }
}

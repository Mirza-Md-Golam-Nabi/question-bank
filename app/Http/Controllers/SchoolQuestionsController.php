<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Question;
use App\Models\SchoolProfile;
use App\Services\SchoolQuestionLibrary;
use App\Support\SchoolConnectionSignature;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A School's own questions as its students read them, inside the school
 * management software's Student panel (CLAUDE.md rule 12). The school's
 * software signs the link with the School's secret for a short time and
 * shows this page in a frame; there is no login here — the school's
 * software has already signed its student in — and nothing is changed.
 */
class SchoolQuestionsController extends Controller
{
    public function __invoke(Request $request, string $code, SchoolQuestionLibrary $library): Response
    {
        $profile = SchoolProfile::query()->with('user')->where('connection_code', $code)->first();

        $isOpen = $profile
            && SchoolConnectionSignature::verifyLink($profile->connection_secret, $code, $request->query('expires'), $request->query('signature'))
            && $profile->user?->role === UserRole::School
            && $profile->user->status === UserStatus::Active;

        abort_unless($isOpen, Response::HTTP_FORBIDDEN);

        // The page is in a frame of another site, where this site's session
        // cookie isn't sent — so the school's software says the language.
        if ($locale = Locale::tryFrom((string) $request->query('lang'))) {
            app()->setLocale($locale->value);
        }

        $school = $profile->user;
        $classSubjects = $library->classSubjects($school);
        $classSubjectId = $classSubjects->firstWhere('id', $request->integer('subject'))['id'] ?? $classSubjects->first()['id'] ?? null;
        $chapters = $classSubjectId ? $library->chapters($school, $classSubjectId) : collect();
        $chapterId = $chapters->firstWhere('id', $request->integer('chapter'))['id'] ?? null;
        $questions = $classSubjectId ? $library->questions($school, $classSubjectId, $chapterId) : null;
        $onPage = collect($questions?->items());

        return response()
            ->view('school-questions.show', [
                'schoolName' => $profile->name,
                'classSubjects' => $classSubjects,
                'classSubjectId' => $classSubjectId,
                'chapters' => $chapters,
                'chapterId' => $chapterId,
                'questions' => $questions,
                'mcqQuestions' => $onPage->filter(fn (Question $question): bool => $question->question_type === QuestionType::Mcq)->values(),
                'cqQuestions' => $onPage->filter(fn (Question $question): bool => $question->question_type === QuestionType::Cq)->values(),
                'linkQuery' => $request->only(['expires', 'signature', 'lang']),
            ])
            ->header('Content-Security-Policy', "frame-ancestors 'self' {$this->schoolSoftwareOrigin($profile)}");
    }

    /**
     * Where the school's software runs — the one other site that may show
     * this page in a frame.
     */
    private function schoolSoftwareOrigin(SchoolProfile $profile): string
    {
        $parts = parse_url((string) $profile->installation_url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}

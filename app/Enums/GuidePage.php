<?php

namespace App\Enums;

use App\Filament\School\Pages\SchoolConnection;
use App\Filament\School\Pages\SendToSchoolResults;
use App\Filament\Staff\Pages\MyEarnings;
use App\Filament\Student\Pages\BuildPracticeExam;
use App\Filament\Student\Pages\PractiseMistakes;
use App\Filament\Support\Pages\MySubscriptionPage;
use App\Filament\Support\Pages\PastPapersPage;
use App\Filament\Support\Pages\Questions\BrowseClassesPage;
use App\Filament\Support\Pages\Questions\CreateQuestionPage;
use App\Filament\Support\Pages\Questions\EditQuestionPage;
use App\Filament\Support\Pages\Questions\ListQuestionsByChapterPage;
use App\Filament\Support\Pages\ReferralsAndCommissionPage;
use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use Filament\Support\Contracts\HasLabel;

/**
 * The pages with a "How do I…?" button (CLAUDE.md rule 14): which panel
 * pages each is, so the button finds its guides by the page being shown.
 * An Admin files a guide under these; a page with no guide for the
 * viewer's role shows no button.
 */
enum GuidePage: string implements HasLabel
{
    case Subscription = 'subscription';
    case WriteQuestions = 'write_questions';
    case BuildExam = 'build_exam';
    case MyExams = 'my_exams';
    case Practice = 'practice';
    case PastPapers = 'past_papers';
    case Commission = 'commission';
    case StaffEarnings = 'staff_earnings';
    case SchoolConnection = 'school_connection';
    case SchoolResults = 'school_results';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Subscription => __('My subscription'),
            self::WriteQuestions => __('My questions (write and edit)'),
            self::BuildExam => __('Select questions (build an exam)'),
            self::MyExams => __('My exams (share, print, results)'),
            self::Practice => __('Practice exams'),
            self::PastPapers => __('Past papers'),
            self::Commission => __('Referrals and commission'),
            self::StaffEarnings => __('My earnings (Staff)'),
            self::SchoolConnection => __('School software connection'),
            self::SchoolResults => __('Send to school results'),
        };
    }

    /**
     * The panel pages it covers — a page class, or a base class every
     * panel's own page extends.
     *
     * @return array<int, class-string>
     */
    public function pageClasses(): array
    {
        return match ($this) {
            self::Subscription => [MySubscriptionPage::class],
            self::WriteQuestions => [BrowseClassesPage::class, ListQuestionsByChapterPage::class, CreateQuestionPage::class, EditQuestionPage::class],
            self::BuildExam => [SelectQuestions::class],
            self::MyExams => [ListExams::class],
            self::Practice => [SelfPracticeExamPage::class, BuildPracticeExam::class, PractiseMistakes::class],
            self::PastPapers => [PastPapersPage::class],
            self::Commission => [ReferralsAndCommissionPage::class],
            self::StaffEarnings => [MyEarnings::class],
            self::SchoolConnection => [SchoolConnection::class],
            self::SchoolResults => [SendToSchoolResults::class],
        };
    }

    /**
     * The one this page (given as its render-hook scopes) is, if any.
     *
     * @param  array<int, string>  $scopes
     */
    public static function forScopes(array $scopes): ?self
    {
        foreach (self::cases() as $page) {
            foreach ($page->pageClasses() as $pageClass) {
                foreach ($scopes as $scope) {
                    if (is_string($scope) && is_a($scope, $pageClass, true)) {
                        return $page;
                    }
                }
            }
        }

        return null;
    }
}

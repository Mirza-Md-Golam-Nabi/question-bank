<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ExamAttempt;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ExamResultPage extends Page
{
    use TranslatesPageLabels;

    protected static ?string $slug = 'exam-result/{attempt}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.student.pages.exam-result-page';

    /**
     * Query flag set when the student was sent here because they tried to
     * start an exam they had already handed in (TakeExamPage::urlFor()).
     */
    public const ALREADY_TAKEN = 'already-taken';

    public ExamAttempt $attempt;

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->student_id === Auth::id(), 403);

        if (request()->boolean(self::ALREADY_TAKEN)) {
            Notification::make()
                ->title(__('You have already taken this exam. Here is your result.'))
                ->body(__('An exam can be taken only once.'))
                ->info()
                ->send();
        }

        $this->attempt = $attempt->load('exam', 'answers.question');
    }
}

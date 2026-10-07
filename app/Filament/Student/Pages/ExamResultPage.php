<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ExamAttempt;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ExamResultPage extends Page
{
    use TranslatesPageLabels;

    protected static ?string $slug = 'exam-result/{attempt}';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.student.pages.exam-result-page';

    public ExamAttempt $attempt;

    public function mount(ExamAttempt $attempt): void
    {
        abort_unless($attempt->student_id === Auth::id(), 403);

        $this->attempt = $attempt->load('exam', 'answers.question');
    }
}

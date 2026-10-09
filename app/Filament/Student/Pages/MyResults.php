<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ExamAttempt;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * The logged-in student's own submitted exams — their way back to a result
 * once a teacher has released the answers, since a teacher's exam shows
 * only the score at the moment it is submitted.
 */
class MyResults extends Page
{
    use TranslatesPageLabels;
    use WithPagination;

    protected string $view = 'filament.student.pages.my-results';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'My results';

    /**
     * @return LengthAwarePaginator<int, ExamAttempt>
     */
    #[Computed]
    public function attempts(): LengthAwarePaginator
    {
        return ExamAttempt::query()
            ->where('student_id', Auth::id())
            ->submitted()
            ->with('exam')
            ->latest('submitted_at')
            ->paginate(15);
    }

    public function resultUrl(ExamAttempt $attempt): string
    {
        return ExamResultPage::getUrl(['attempt' => $attempt->id]);
    }
}

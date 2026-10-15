<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use App\Services\StudentMistakeTracker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Self-practice on the student's own mistakes: an exam drawn from the
 * questions they got wrong, in a subject of their choosing. Which
 * questions those are, and when one leaves the list, is
 * StudentMistakeTracker's business.
 */
class PractiseMistakes extends SelfPracticeExamPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $title = 'Practise my mistakes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->options(fn (): array => $this->subjectsWithMistakes())
                    ->helperText(fn (): ?string => $this->subjectsWithMistakes() === []
                        ? __('You have no mistakes to practise right now. Questions you get wrong in an exam will appear here.')
                        : __('Only subjects where you have mistakes are listed, with how many.'))
                    ->required(),
                TextInput::make('question_count')
                    ->label(__('How many questions'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(10)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submitLabel(): string
    {
        return __('Start practising');
    }

    protected function createAttempt(User $student, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateFromMistakes(
            $student,
            (int) $data['subject_id'],
            (int) $data['question_count'],
        );
    }

    /**
     * The subjects the student has mistakes in, each labelled with how
     * many — worked out once per request.
     *
     * @return array<int, string>
     */
    private function subjectsWithMistakes(): array
    {
        return once(function (): array {
            $counts = app(StudentMistakeTracker::class)->countsBySubject(Auth::user());

            return Subject::query()
                ->whereKey($counts->keys())
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Subject $subject): array => [
                    $subject->id => "{$subject->display_name} ({$counts[$subject->id]})",
                ])
                ->all();
        });
    }
}

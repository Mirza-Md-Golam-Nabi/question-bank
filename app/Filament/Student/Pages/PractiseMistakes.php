<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\ClassSubject;
use App\Models\ExamAttempt;
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

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Practice;

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Practise my mistakes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('class_subject_id')
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

    protected function createAttempt(User $student, ClassSubject $classSubject, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateFromMistakes(
            $student,
            $classSubject,
            (int) $data['question_count'],
        );
    }

    /**
     * The subjects of the student's class they have mistakes in, each
     * labelled with how many — worked out once per request.
     *
     * @return array<int, string>
     */
    private function subjectsWithMistakes(): array
    {
        return once(function (): array {
            if (! $this->hasClass()) {
                return [];
            }

            $counts = app(StudentMistakeTracker::class)->countsByClassSubject(Auth::user(), Auth::user()->academic_class_id);

            return collect($this->subjectOptions())
                ->only($counts->keys())
                ->map(fn (string $name, int $classSubjectId): string => "{$name} ({$counts[$classSubjectId]})")
                ->all();
        });
    }
}

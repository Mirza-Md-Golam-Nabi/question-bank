<?php

namespace App\Filament\Student\Pages;

use App\Enums\Difficulty;
use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\Chapter;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Self-practice, Auto-Generate mode: the system draws random questions of
 * the chosen subject (and optionally chapters / difficulty).
 */
class GeneratePracticeExam extends SelfPracticeExamPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $title = 'Auto-Generate Practice Exam';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->subjectSelect(),
                Select::make('chapter_ids')
                    ->label(__('Chapters (optional — leave blank for all)'))
                    ->multiple()
                    ->options(fn (Get $get) => $get('subject_id')
                        ? Chapter::query()
                            ->whereHas('classSubject', fn ($q) => $q->where('subject_id', $get('subject_id')))
                            ->ordered()
                            ->pluck('name', 'id')
                        : []),
                Select::make('difficulty')
                    ->options(Difficulty::class),
                TextInput::make('question_count')
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
        return __('Generate exam');
    }

    protected function createAttempt(User $student, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateAuto(
            $student,
            $data['subject_id'],
            $this->difficultyFrom($data['difficulty'] ?? null),
            $data['question_count'],
            $data['chapter_ids'] ?: null,
        );
    }

    /**
     * A select whose options are an enum hands back the enum case itself,
     * not its string value — so accept either, and nothing when left blank.
     */
    private function difficultyFrom(mixed $state): ?Difficulty
    {
        return $state instanceof Difficulty ? $state : Difficulty::tryFrom((string) $state);
    }
}

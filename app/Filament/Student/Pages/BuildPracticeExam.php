<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\SelfPracticeExamService;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Self-practice, Manual Selection mode: the student picks the questions
 * from the approved pool themselves.
 */
class BuildPracticeExam extends SelfPracticeExamPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'Build Your Own Practice Exam';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->subjectSelect(fn ($set) => $set('question_ids', [])),
                Select::make('question_ids')
                    ->label(__('Questions'))
                    ->options(fn (Get $get) => Question::approvedOptionsForSubject($get('subject_id')))
                    ->multiple()
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submitLabel(): string
    {
        return __('Build exam');
    }

    protected function createAttempt(User $student, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateManual($student, $data['subject_id'], $data['question_ids']);
    }
}

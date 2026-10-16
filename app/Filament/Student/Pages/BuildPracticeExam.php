<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\SelfPracticeExamPage;
use App\Models\ClassSubject;
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

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Practice;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Build Your Own Practice Exam';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->subjectSelect(fn ($set) => $set('question_ids', [])),
                Select::make('question_ids')
                    ->label(__('Questions'))
                    // Searched on the server, a short list at a time — the
                    // subject's whole pool is never sent to the browser.
                    // Only questions of a difficulty the student's plan allows.
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => $this->classSubject($get('class_subject_id'))
                        ? Question::approvedOptionsForClassSubject($get('class_subject_id'), $search, $this->allowedDifficulties($get('class_subject_id')))
                        : [])
                    ->getOptionLabelsUsing(fn (array $values): array => Question::approvedOptionLabels($values))
                    ->helperText(__('Type a few words of a question to find it.'))
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

    protected function createAttempt(User $student, ClassSubject $classSubject, array $data): ExamAttempt
    {
        return app(SelfPracticeExamService::class)->generateManual($student, $classSubject, $data['question_ids']);
    }
}

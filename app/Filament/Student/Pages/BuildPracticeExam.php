<?php

namespace App\Filament\Student\Pages;

use App\Enums\ExamType;
use App\Models\Question;
use App\Models\Subject;
use App\Services\SelfPracticeExamService;
use App\Services\SubscriptionLimitService;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class BuildPracticeExam extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.student.pages.build-practice-exam';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $title = 'Build Your Own Practice Exam';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('question_ids', [])),
                Select::make('question_ids')
                    ->label('Questions')
                    ->options(fn (Get $get) => $get('subject_id')
                        ? Question::approvedPool()
                            ->whereHas('chapter.classSubject', fn ($q) => $q->where('subject_id', $get('subject_id')))
                            ->get()
                            ->mapWithKeys(fn (Question $question) => [$question->id => strip_tags($question->question_text)])
                        : [])
                    ->multiple()
                    ->searchable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function build(): void
    {
        $student = Auth::user();

        if (app(SubscriptionLimitService::class)->hasReachedMonthlyLimit($student, ExamType::SelfPractice)) {
            Notification::make()
                ->title('Monthly free limit reached')
                ->body('Upgrade your subscription to generate more practice exams this month.')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $attempt = app(SelfPracticeExamService::class)->generateManual(
            $student,
            $data['subject_id'],
            $data['question_ids'],
        );

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }
}

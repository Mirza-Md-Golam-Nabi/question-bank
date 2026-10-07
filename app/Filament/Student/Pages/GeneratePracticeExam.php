<?php

namespace App\Filament\Student\Pages;

use App\Enums\Difficulty;
use App\Enums\ExamType;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\Chapter;
use App\Models\Subject;
use App\Services\SelfPracticeExamService;
use App\Services\SubscriptionLimitService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class GeneratePracticeExam extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use TranslatesPageLabels;

    protected string $view = 'filament.student.pages.generate-practice-exam';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $title = 'Auto-Generate Practice Exam';

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
                    ->label(__('Subject'))
                    ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required(),
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

    public function generate(): void
    {
        $student = Auth::user();

        if (app(SubscriptionLimitService::class)->hasReachedMonthlyLimit($student, ExamType::SelfPractice)) {
            Notification::make()
                ->title(__('Monthly free limit reached'))
                ->body(__('Upgrade your subscription to generate more practice exams this month.'))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $attempt = app(SelfPracticeExamService::class)->generateAuto(
            $student,
            $data['subject_id'],
            isset($data['difficulty']) ? Difficulty::from($data['difficulty']) : null,
            $data['question_count'],
            $data['chapter_ids'] ?: null,
        );

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }
}

<?php

namespace App\Filament\Student\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class JoinExam extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use TranslatesPageLabels;

    protected string $view = 'filament.student.pages.join-exam';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

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
                TextInput::make('share_token')
                    ->label(__('Share link or code'))
                    ->required(),
            ])
            ->statePath('data');
    }

    public function join(): void
    {
        $data = $this->form->getState();
        $shareToken = trim(str($data['share_token'])->afterLast('/'));

        $exam = Exam::findPublishedByShareToken($shareToken);

        if (! $exam?->isAcceptingAttempts()) {
            Notification::make()->title(__('This exam link is not active.'))->danger()->send();

            return;
        }

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => Auth::id(),
            'is_guest' => false,
            'started_at' => now(),
        ]);

        $this->redirect(TakeExamPage::getUrl(['attempt' => $attempt->id]));
    }
}

<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\FutureDateTimePicker;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Services\ExamRepeater;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListExams extends ListRecords
{
    use NotifiesValidationFailures;

    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Exams are built from the question picker (CLAUDE.md rule 10).
            CreateAction::make()->url(fn (): string => SelectQuestions::getUrl()),
        ];
    }

    /**
     * "Take again" (ExamsTable::takeAgainAction()): the new exam, or the
     * reason it can't be made.
     *
     * @param  array{title: string, duration_minutes: int|string, link_expires_at?: mixed}  $data
     */
    public function takeAgain(Exam $exam, array $data, Action $action): void
    {
        $repeated = $this->rescueWithNotification(fn (): Exam => app(ExamRepeater::class)->repeat(
            $exam,
            Auth::user(),
            $data['title'],
            (int) $data['duration_minutes'],
            FutureDateTimePicker::parse($data['link_expires_at'] ?? null),
        ), $action);

        if ($repeated) {
            Notification::make()
                ->title(__('New exam created'))
                ->body(__(':title is saved as a draft with its own link. Publish it to share with the new students.', ['title' => $repeated->title]))
                ->success()
                ->send();
        }
    }
}

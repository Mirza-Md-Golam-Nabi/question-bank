<?php

namespace App\Filament\Teacher\Pages;

use App\Enums\ExamDeliveryMode;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\Pages\PastPapersPage;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use App\Models\BillingSetting;
use App\Models\BoardQuestionPaper;
use App\Services\PastPaperExamService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * A teacher sets a whole past paper as an exam for their students — online
 * through the share link (its MCQs), printed (MCQ and CQ), or both. The exam
 * then lives in their Exams list like any other.
 */
class PastPapers extends PastPapersPage
{
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Exams;

    protected static ?int $navigationSort = 2;

    protected function showsMcqsInPreview(): bool
    {
        return true;
    }

    protected function paperActions(): array
    {
        return [
            Action::make('setAsExam')
                ->label(__('Set as exam'))
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->modalHeading(fn (BoardQuestionPaper $record): string => $record->title())
                ->modalDescription(__('The whole paper becomes one of your exams. Online, students sit its MCQs; the printed paper has the CQs too.'))
                ->modalSubmitActionLabel(__('Create exam'))
                ->fillForm(fn (BoardQuestionPaper $record): array => [
                    'delivery_mode' => $record->mcq_questions_count > 0 ? ExamDeliveryMode::Online : ExamDeliveryMode::Offline,
                    'duration_minutes' => $record->duration_minutes ?: max(1, $record->mcq_questions_count),
                    'negative_marks' => $record->negative_marks ?: BillingSetting::current()->default_negative_marks,
                ])
                ->schema(fn (BoardQuestionPaper $record): array => [
                    Radio::make('delivery_mode')
                        ->label(__('Exam mode'))
                        ->options(ExamDeliveryMode::class)
                        // Without MCQs there is nothing to sit online.
                        ->disableOptionWhen(fn (string $value): bool => $record->mcq_questions_count === 0 && ExamDeliveryMode::from($value)->includesOnline())
                        ->live()
                        ->required(),
                    TextInput::make('duration_minutes')
                        ->label(__('Time (minutes)'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('negative_marks')
                        ->label(__('Marks lost per wrong MCQ answer'))
                        ->helperText(__('0 for no negative marking.'))
                        ->numeric()
                        ->step(0.05)
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => self::deliveryModeOf($get('delivery_mode'))?->includesOnline() ?? false),
                ])
                ->action(function (BoardQuestionPaper $record, array $data, Action $action): void {
                    $this->rescueWithNotification(fn () => app(PastPaperExamService::class)->createTeacherExam(
                        Auth::user(),
                        $record,
                        self::deliveryModeOf($data['delivery_mode']),
                        (int) $data['duration_minutes'],
                        isset($data['negative_marks']) ? (float) $data['negative_marks'] : null,
                    ), $action);

                    Notification::make()
                        ->title(__('Exam created'))
                        ->body(__('Publish it or print it from your Exams list.'))
                        ->success()
                        ->send();

                    $this->redirect(ExamResource::getUrl());
                }),
        ];
    }

    /**
     * A select whose options are an enum hands back the enum case itself.
     */
    private static function deliveryModeOf(mixed $state): ?ExamDeliveryMode
    {
        return $state instanceof ExamDeliveryMode ? $state : ExamDeliveryMode::tryFrom((string) $state);
    }
}

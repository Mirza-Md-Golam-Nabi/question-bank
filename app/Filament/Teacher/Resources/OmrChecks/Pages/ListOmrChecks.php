<?php

namespace App\Filament\Teacher\Resources\OmrChecks\Pages;

use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\CurrentPanel;
use App\Filament\Teacher\Resources\OmrChecks\OmrCheckResource;
use App\Filament\Teacher\Resources\OmrChecks\Schemas\OmrCheckForm;
use App\Models\Exam;
use App\Services\OmrCheckService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * The OMR checks, and making a new one: the form in a window, saved by
 * OmrCheckService — then straight on to its OMR page, to print the sheet.
 */
class ListOmrChecks extends ListRecords
{
    use NotifiesValidationFailures;

    protected static string $resource = OmrCheckResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('Check OMR sheets of a question paper made outside the system: give its answer key, print the OMR sheet, then scan the filled-in sheets with your phone.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('New OMR check'))
                ->icon('heroicon-o-plus')
                ->modalHeading(__('New OMR check'))
                ->modalWidth(Width::FourExtraLarge)
                ->fillForm(fn (): array => OmrCheckForm::fill())
                ->schema(OmrCheckForm::components())
                ->modalSubmitActionLabel(__('Save'))
                ->action(fn (array $data, Action $action) => $this->saveCheck($data, $action)),
        ];
    }

    /**
     * Saves a new check, or `$exam`'s answer key — or says why not.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveCheck(array $data, Action $action, ?Exam $exam = null): void
    {
        $saved = $this->rescueWithNotification(
            fn (): Exam => app(OmrCheckService::class)->save(Auth::user(), OmrCheckForm::dataFor($data), $exam),
            $action,
        );

        if (! $saved) {
            return;
        }

        Notification::make()->title(__('OMR check saved'))->success()->send();

        if (! $exam) {
            $this->redirect(CurrentPanel::route('exams.omr-scan', $saved));
        }
    }
}

<?php

namespace App\Filament\Support\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\ReactivationRequest;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the Teacher and Staff panels: where a suspended user asks an
 * Admin to switch their account back on, and sees what became of the
 * request. Only there for a suspended user — everyone else has nothing to
 * ask for. Who may ask, and how often, is ReactivationRequest's rule.
 */
abstract class RequestReactivationPage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.request-reactivation';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->isSuspended() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('message')
                    ->label(__('Why should your account be reactivated?'))
                    ->rows(4)
                    ->required()
                    ->minLength(10)
                    ->maxLength(1000),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        // Read outside the try: the form's own validation errors belong on
        // its fields, not in the notification below.
        $message = $this->form->getState()['message'];

        try {
            ReactivationRequest::submitFor(Auth::user(), $message);
        } catch (ValidationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->form->fill();

        Notification::make()->title(__('Your request has been sent to the admin.'))->success()->send();
    }

    public function latestRequest(): ?ReactivationRequest
    {
        return ReactivationRequest::latestFor(Auth::user());
    }

    public function canSubmit(): bool
    {
        return ReactivationRequest::canBeSubmittedBy(Auth::user());
    }
}

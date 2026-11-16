<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\BillingSetting;
use App\Services\QuestionSyncSource;
use App\Support\QuestionSync;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * The live server's switch for sharing its questions with the test server
 * (CLAUDE.md rule 16) — only there, only for the Super Admin. Opened for so
 * many hours, it closes by itself after them; closed, the test server gets
 * nothing at all. What is shared is QuestionSyncSource's.
 */
class QuestionSharing extends Page
{
    use NotifiesValidationFailures;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.question-sharing';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCloudArrowUp;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::QuestionBank;

    protected static ?string $title = 'Share with the test server';

    public static function canAccess(): bool
    {
        return QuestionSync::isSource() && QuestionSync::canManage(Auth::user());
    }

    /**
     * Whether this server holds the secret the test server signs with.
     */
    public function hasSecret(): bool
    {
        return QuestionSync::secret() !== '';
    }

    public function openUntil(): ?string
    {
        $until = QuestionSyncSource::openUntil();

        return $until ? QuestionSync::displayTime($until) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label(fn (): string => QuestionSyncSource::isOpen() ? __('Open for longer') : __('Open the sharing'))
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->disabled(fn (): bool => ! $this->hasSecret())
                ->modalDescription(__('The test server can fetch questions until the hours are over; then the sharing closes by itself.'))
                ->fillForm(fn (): array => ['hours' => BillingSetting::current()->question_sync_open_hours])
                ->schema([
                    TextInput::make('hours')
                        ->label(__('Open for (hours)'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(QuestionSyncSource::MAX_OPEN_HOURS)
                        ->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    $opened = $this->rescueWithNotification(function () use ($data): bool {
                        QuestionSyncSource::open(Auth::user(), (int) $data['hours']);

                        return true;
                    }, $action);

                    $opened && Notification::make()->title(__('The sharing is open.'))->success()->send();
                }),
            Action::make('close')
                ->label(__('Close the sharing'))
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (): bool => QuestionSyncSource::isOpen())
                ->requiresConfirmation()
                ->action(function (): void {
                    $closed = $this->rescueWithNotification(function (): bool {
                        QuestionSyncSource::close(Auth::user());

                        return true;
                    });

                    $closed && Notification::make()->title(__('The sharing is closed.'))->success()->send();
                }),
        ];
    }
}

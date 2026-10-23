<?php

namespace App\Filament\School\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\SchoolProfile;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * What the school's computer operator puts into the school management
 * software to connect it: this platform's address, the School's connection
 * code and its secret — and whether the software has connected. A new
 * secret disconnects it until it is given the new one.
 */
class SchoolConnection extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.school.pages.school-connection';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'School software connection';

    #[Computed]
    public function profile(): SchoolProfile
    {
        return SchoolProfile::for(Auth::user());
    }

    public function platformUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function regenerateSecretAction(): Action
    {
        return Action::make('regenerateSecret')
            ->label(__('Make a new secret'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('The school management software is disconnected until the new secret is put into it. Do this if the old secret may have been seen by someone else.'))
            ->action(function (): void {
                $this->profile->regenerateSecret();
                unset($this->profile);

                Log::info('School connection secret regenerated.', ['school_id' => Auth::id()]);

                Notification::make()->title(__('A new secret has been made. Put it into the school management software.'))->success()->send();
            });
    }
}

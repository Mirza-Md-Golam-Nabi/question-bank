<?php

namespace App\Filament\School\Pages;

use App\Filament\Support\Pages\PendingApprovalPage;
use App\Filament\Support\SchoolDetailsForm;
use App\Models\SchoolProfile;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * Where a new School account waits for an Admin: it tells the Admin which
 * school it is — the name, EIIN, address and phone the Admin checks before
 * approving it to speak for that school.
 */
class PendingApproval extends PendingApprovalPage
{
    protected string $view = 'filament.school.pages.pending-approval';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SchoolProfile::for(Auth::user())->only(['name', 'eiin', 'address', 'phone']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(SchoolDetailsForm::components())
            ->statePath('data');
    }

    public function save(): void
    {
        SchoolProfile::for(Auth::user())->update($this->form->getState());

        Notification::make()->title(__('Saved. An admin will check them and approve your account.'))->success()->send();
    }
}

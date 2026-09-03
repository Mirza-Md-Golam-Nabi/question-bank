<?php

namespace App\Filament\Staff\Pages;

use App\Models\StaffEarning;
use App\Models\StaffProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;

class MyEarnings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.staff.pages.my-earnings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $profile = $this->profile();

        $this->form->fill([
            'bank_account_number' => $profile?->bank_account_number,
            'bank_name' => $profile?->bank_name,
            'branch_name' => $profile?->branch_name,
            'account_holder_name' => $profile?->account_holder_name,
            'mobile_banking_number' => $profile?->mobile_banking_number,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('account_holder_name')->required(),
                TextInput::make('bank_name'),
                TextInput::make('branch_name'),
                TextInput::make('bank_account_number'),
                TextInput::make('mobile_banking_number')->label('Mobile banking number (bKash/Nagad)'),
            ])
            ->statePath('data');
    }

    public function saveBankInfo(): void
    {
        $data = $this->form->getState();

        StaffProfile::updateOrCreate(['user_id' => Auth::id()], $data);

        Notification::make()->title('Bank info saved')->success()->send();
    }

    public function profile(): ?StaffProfile
    {
        return StaffProfile::where('user_id', Auth::id())->first();
    }

    /**
     * @return Collection<int, StaffEarning>
     */
    public function earnings(): Collection
    {
        return StaffEarning::where('staff_id', Auth::id())
            ->with('question.chapter.classSubject.subject')
            ->latest('created_at')
            ->get();
    }

    public function subjectBreakdown(): SupportCollection
    {
        return $this->earnings()
            ->groupBy(fn (StaffEarning $earning) => $earning->question->chapter->classSubject->subject->name ?? 'Unknown')
            ->map(fn (Collection $earnings) => [
                'count' => $earnings->count(),
                'total' => $earnings->sum('amount'),
            ]);
    }
}

<?php

namespace App\Filament\Resources\StaffPayouts\Pages;

use App\Enums\StaffEarningStatus;
use App\Filament\Resources\StaffPayouts\StaffPayoutResource;
use App\Models\StaffPayout;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffPayouts extends ManageRecords
{
    protected static string $resource = StaffPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPayout')
                ->label(__('Create payout'))
                ->schema([
                    Select::make('staff_id')
                        ->label(__('Staff'))
                        ->options(fn () => User::query()
                            ->whereHas('staffEarnings', fn ($q) => $q->where('status', StaffEarningStatus::PendingPayout))
                            ->get()
                            ->mapWithKeys(fn (User $staff) => [
                                $staff->id => "{$staff->name} (৳".$staff->staffEarnings()
                                    ->where('status', StaffEarningStatus::PendingPayout)
                                    ->sum('amount').' pending)',
                            ]))
                        ->required(),
                    TextInput::make('reference_note')
                        ->label(__('Reference note (e.g. bKash TrxID)')),
                ])
                ->action(function (array $data) {
                    $staff = User::findOrFail($data['staff_id']);
                    $payout = StaffPayout::createFor($staff, auth()->user(), $data['reference_note'] ?? null);

                    Notification::make()
                        ->title(__('Paid out ৳:amount to :name', ['amount' => $payout->total_amount, 'name' => $staff->name]))
                        ->success()
                        ->send();
                }),
        ];
    }
}

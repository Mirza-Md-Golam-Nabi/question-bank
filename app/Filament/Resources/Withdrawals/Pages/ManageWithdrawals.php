<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Filament\Resources\Withdrawals\WithdrawalResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * No create button: a withdrawal is only ever asked for by the teacher.
 */
class ManageWithdrawals extends ManageRecords
{
    protected static string $resource = WithdrawalResource::class;
}

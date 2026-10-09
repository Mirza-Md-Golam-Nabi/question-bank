<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageWalletTransactions extends ManageRecords
{
    protected static string $resource = WalletTransactionResource::class;

    // No CreateAction — the ledger is only written by WalletService.
    protected function getHeaderActions(): array
    {
        return [];
    }
}

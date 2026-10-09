<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    // No CreateAction — a payment only comes from a customer's purchase.
    protected function getHeaderActions(): array
    {
        return [];
    }
}

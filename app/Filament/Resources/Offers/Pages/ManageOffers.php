<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\OfferResource;
use App\Filament\Support\Concerns\NotifiesValidationFailures;
use App\Models\Offer;
use App\Services\OfferNotifier;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageOffers extends ManageRecords
{
    use NotifiesValidationFailures;

    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'created_by' => Auth::id()]),
        ];
    }

    /**
     * @param  array{to_bell?: bool, to_push?: bool}  $data
     */
    public function send(Offer $offer, array $data, Action $action): void
    {
        $sent = $this->rescueWithNotification(function () use ($offer, $data): bool {
            app(OfferNotifier::class)->send($offer, Auth::user(), (bool) ($data['to_bell'] ?? false), (bool) ($data['to_push'] ?? false));

            return true;
        }, $action);

        if ($sent) {
            Notification::make()->title(__('The offer is being sent.'))->success()->send();
        }
    }
}

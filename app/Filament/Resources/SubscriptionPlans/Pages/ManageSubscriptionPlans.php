<?php

namespace App\Filament\Resources\SubscriptionPlans\Pages;

use App\Enums\SubscriptionTargetRole;
use App\Filament\Resources\SubscriptionPlans\SubscriptionPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ManageSubscriptionPlans extends ManageRecords
{
    protected static string $resource = SubscriptionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * The All / Student / Teacher tabs above the table. Unlike Filament's own
     * tabs they filter in the browser, without asking the server again.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.subscription-plans.role-tabs')
                    ->viewData(['tabs' => $this->roleTabs()]),
                EmbeddedTable::make(),
            ]);
    }

    /**
     * @return array<string, string> Tab key (a target role, or "all") => label.
     */
    public function roleTabs(): array
    {
        return [
            'all' => __('All'),
            ...collect([SubscriptionTargetRole::Student, SubscriptionTargetRole::Teacher])
                ->mapWithKeys(fn (SubscriptionTargetRole $role): array => [$role->value => $role->getLabel()])
                ->all(),
        ];
    }
}

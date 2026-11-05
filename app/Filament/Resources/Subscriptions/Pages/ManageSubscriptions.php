<?php

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Support\GrantSubscriptionAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageSubscriptions extends ManageRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GrantSubscriptionAction::make(),
        ];
    }

    /**
     * All, then one tab per role that holds subscriptions — by whose
     * subscription it is.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make(__('All'))];

        foreach (UserRole::subscribers() as $role) {
            $tabs[$role->value] = Tab::make($role->getLabel())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->where('role', $role)));
        }

        return $tabs;
    }

    /**
     * The same small, wrapping tabs as the "Users" page.
     */
    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()->extraAttributes(['class' => 'qb-compact-tabs']);
    }
}

<?php

namespace App\Filament\Support\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Livewire\Attributes\Url;

/**
 * A question list ordered by ID — the oldest first, or the latest — with
 * the two buttons that choose, the chosen one coloured. The choice is in
 * the URL (`?order=latest`), and the list starts again from its first page.
 * A page puts orderDirection() on its query and shows the buttons.
 */
trait OrdersOldestOrLatest
{
    /**
     * 'oldest' or 'latest'.
     */
    #[Url(as: 'order')]
    public string $order = 'oldest';

    public function orderDirection(): string
    {
        return $this->order === 'latest' ? 'desc' : 'asc';
    }

    public function oldestAction(): Action
    {
        return $this->orderAction('oldest', __('Oldest'), 'heroicon-m-bars-arrow-down');
    }

    public function latestAction(): Action
    {
        return $this->orderAction('latest', __('Latest'), 'heroicon-m-bars-arrow-up');
    }

    /**
     * The two as one dropdown, "Sort by: …" the order in use.
     */
    public function orderDropdown(): ActionGroup
    {
        return ActionGroup::make([$this->oldestAction(), $this->latestAction()])
            // Read when drawn, not when built: the table is built before an
            // order chosen in the same request takes effect.
            ->label(fn (): string => __('Sort by: :order', ['order' => $this->order === 'latest' ? __('Latest') : __('Oldest')]))
            ->icon('heroicon-m-arrows-up-down')
            ->color('gray')
            ->button();
    }

    private function orderAction(string $order, string $label, string $icon): Action
    {
        return Action::make($order)
            ->label($label)
            ->icon($icon)
            ->size('sm')
            ->color(fn (): string => $this->order === $order ? 'primary' : 'gray')
            ->action(function () use ($order): void {
                $this->order = $order;
                $this->resetPage();
            });
    }
}

<?php

namespace App\Filament\Support\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * A question list shown as whole-question cards by default, with a button
 * to switch to the table's columns and back — the choice kept in the URL
 * (`?view=table`), so it survives a reload. The page says where its list
 * is (displayUrl()) and what a card holds.
 */
trait SwitchesCardAndTableView
{
    /**
     * 'cards' or 'table'.
     */
    #[Url(as: 'view')]
    public string $display = 'cards';

    /**
     * This page in the given display: 'cards' or 'table'.
     */
    abstract protected function displayUrl(string $display): string;

    public function showsCards(): bool
    {
        return $this->display !== 'table';
    }

    protected function switchDisplayAction(): Action
    {
        $toTable = $this->showsCards();

        return Action::make('switchDisplay')
            ->label($toTable ? __('Table view') : __('Card view'))
            ->icon($toTable ? Heroicon::OutlinedTableCells : Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->url($this->displayUrl($toTable ? 'table' : 'cards'));
    }
}

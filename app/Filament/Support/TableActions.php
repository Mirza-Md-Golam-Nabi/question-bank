<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

/**
 * The row and bulk actions most resource tables end with — edit, delete,
 * and bulk delete — so a table that needs nothing more doesn't spell them
 * out again.
 */
class TableActions
{
    /**
     * @param  bool  $iconButtons  Show the two actions as icons only, for tables short on width.
     * @return array<Action>
     */
    public static function editAndDelete(bool $iconButtons = false): array
    {
        return $iconButtons
            ? [EditAction::make()->iconButton(), DeleteAction::make()->iconButton()]
            : [EditAction::make(), DeleteAction::make()];
    }

    /**
     * @return array<BulkActionGroup>
     */
    public static function bulkDelete(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ];
    }
}

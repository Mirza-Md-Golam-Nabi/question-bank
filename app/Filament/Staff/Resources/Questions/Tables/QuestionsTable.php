<?php

namespace App\Filament\Staff\Resources\Questions\Tables;

use App\Filament\Support\QuestionsTable as SharedQuestionsTable;
use Filament\Tables\Table;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return SharedQuestionsTable::configure($table);
    }
}

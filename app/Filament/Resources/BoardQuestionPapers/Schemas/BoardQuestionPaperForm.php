<?php

namespace App\Filament\Resources\BoardQuestionPapers\Schemas;

use App\Filament\Support\BoardQuestionPaperFormSchema;
use Filament\Schemas\Schema;

class BoardQuestionPaperForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(BoardQuestionPaperFormSchema::components());
    }
}

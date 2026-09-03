<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Filament\Support\QuestionFormSchema;
use Filament\Schemas\Schema;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(QuestionFormSchema::components());
    }
}

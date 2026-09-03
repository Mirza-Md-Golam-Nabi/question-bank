<?php

namespace App\Filament\Teacher\Resources\Exams\Schemas;

use App\Filament\Support\ExamFormSchema;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(ExamFormSchema::components());
    }
}

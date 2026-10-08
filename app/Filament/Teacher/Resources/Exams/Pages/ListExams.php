<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Exams are built from the question picker (CLAUDE.md rule 10).
            CreateAction::make()->url(fn (): string => SelectQuestions::getUrl()),
        ];
    }
}

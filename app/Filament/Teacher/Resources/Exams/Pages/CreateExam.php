<?php

namespace App\Filament\Teacher\Resources\Exams\Pages;

use App\Enums\ExamType;
use App\Filament\Teacher\Resources\Exams\ExamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['exam_type'] = ExamType::TeacherExam;

        return $data;
    }
}

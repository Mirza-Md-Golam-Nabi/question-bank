<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExamType: string implements HasLabel
{
    case TeacherExam = 'teacher_exam';
    case SelfPractice = 'self_practice';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TeacherExam => 'Teacher Exam',
            self::SelfPractice => 'Self Practice',
        };
    }
}

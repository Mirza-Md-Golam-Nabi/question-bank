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
            self::TeacherExam => __('Teacher Exam'),
            self::SelfPractice => __('Self Practice'),
        };
    }
}

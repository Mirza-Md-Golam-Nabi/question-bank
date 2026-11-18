<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExamType: string implements HasLabel
{
    case TeacherExam = 'teacher_exam';
    case SelfPractice = 'self_practice';
    /** Only OMR sheets checked against an answer key the teacher typed in — no questions (CLAUDE.md rule 10.12). */
    case OmrCheck = 'omr_check';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::TeacherExam => __('Teacher Exam'),
            self::SelfPractice => __('Self Practice'),
            self::OmrCheck => __('OMR check'),
        };
    }
}

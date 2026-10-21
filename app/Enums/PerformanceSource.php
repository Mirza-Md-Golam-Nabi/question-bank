<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Query\Builder;

/**
 * Which of a student's exams their performance analytics are worked out
 * from: all of them together (the default), or one kind — their own
 * practice, their teachers' exams, or whole past papers. A past paper is
 * its own kind whoever set it, since its questions are not the pool's.
 */
enum PerformanceSource: string implements HasLabel
{
    case All = 'all';
    case SelfPractice = 'self_practice';
    case TeacherExam = 'teacher_exam';
    case PastPaper = 'past_paper';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::All => __('All exams'),
            self::SelfPractice => __('My own practice'),
            self::TeacherExam => __("Teachers' exams"),
            self::PastPaper => __('Past papers'),
        };
    }

    /**
     * Narrows a query joined to `exams` to this kind of exam.
     */
    public function constrain(Builder $exams): Builder
    {
        return match ($this) {
            self::All => $exams,
            self::SelfPractice => $exams->where('exams.exam_type', ExamType::SelfPractice->value)->whereNull('exams.board_question_paper_id'),
            self::TeacherExam => $exams->where('exams.exam_type', ExamType::TeacherExam->value)->whereNull('exams.board_question_paper_id'),
            self::PastPaper => $exams->whereNotNull('exams.board_question_paper_id'),
        };
    }
}

<?php

namespace App\Models;

use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'exam_id', 'student_id', 'is_guest', 'guest_name', 'guest_contact',
    'started_at', 'submitted_at', 'status', 'total_score',
])]
class ExamAttempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_guest' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'status' => ExamAttemptStatus::class,
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class, 'attempt_id');
    }

    /**
     * Auto-grades every MCQ answer against whichever of the question's
     * `options` carries `is_correct` and sums the result into total_score.
     * CQ answers are left for a teacher to grade manually (obtained_marks
     * stays whatever it already was — 0 by default) — only the MCQ portion
     * of total_score is ever computed here.
     */
    public function submitAndAutoGrade(): void
    {
        $examQuestionsById = $this->exam->questions->keyBy('id');

        foreach ($this->answers as $answer) {
            $question = $answer->question;

            if ($question->question_type !== QuestionType::Mcq) {
                continue;
            }

            $correctOption = collect($question->options)
                ->first(fn (array $option) => (bool) ($option['is_correct'] ?? false));

            $isCorrect = $correctOption && $answer->student_answer === $correctOption['option'];
            $marks = $examQuestionsById->get($question->id)?->pivot?->marks_override ?? $question->marks;

            $answer->update([
                'is_correct' => $isCorrect,
                'obtained_marks' => $isCorrect ? $marks : 0,
            ]);
        }

        $this->forceFill([
            'status' => ExamAttemptStatus::Submitted,
            'submitted_at' => now(),
            'total_score' => $this->answers()->sum('obtained_marks'),
        ])->save();
    }
}

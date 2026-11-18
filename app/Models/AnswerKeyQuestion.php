<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Models\Concerns\HasMcqOptions;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One question of an OMR check (CLAUDE.md rule 10.12): there is no question
 * at all, only its number on the sheet, how many options it has, its marks
 * and which option is right — what the teacher typed in as the answer key
 * (Exam::answerKey()). Made from the exam, never saved, so the sheet
 * (App\Support\OmrSheet), the answers kept (AttemptAnswer, by number) and
 * the grading (ExamAttempt::submitAndAutoGrade()) take it like any MCQ.
 *
 * An option is its letter's English form ("A", "B", …) — what an answer
 * to it is kept as, whatever language the page is in.
 */
class AnswerKeyQuestion extends Model
{
    use HasMcqOptions;

    /** What an option is kept as: its place's letter, A first. */
    public const OPTION_VALUES = ['A', 'B', 'C', 'D', 'E', 'F'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'options' => 'array',
            'marks' => 'float',
        ];
    }

    /**
     * Question `$number` of a key with `$optionCount` options, `$correct`
     * (0 = the first) being right.
     */
    public static function numbered(int $number, int $optionCount, int $correct, float $marks): self
    {
        return (new self)->forceFill([
            'id' => $number,
            'question_type' => QuestionType::Mcq,
            'question_text' => null,
            'question_image' => null,
            'marks' => $marks,
            'options' => array_map(
                fn (int $option): array => ['option' => self::OPTION_VALUES[$option], 'image' => null, 'is_correct' => $option === $correct],
                range(0, $optionCount - 1),
            ),
        ]);
    }

    /**
     * Which option an answer kept for it is (0 = the first): null when
     * blank, or for more than one circle filled in.
     */
    public static function optionIndexOf(?string $answer): ?int
    {
        $index = array_search($answer, self::OPTION_VALUES, true);

        return $index === false ? null : $index;
    }

    /**
     * There is nothing to save: the key is kept on the exam.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        throw new LogicException('An OMR check\'s question is made from its exam\'s answer key and is never saved.');
    }
}

<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lays questions out as a paper does: an MCQ under a shared stimulus never
 * stands alone — the questions sharing one are kept together, in their own
 * order, at the place the first of them comes — and each run of them gets
 * the stimulus once, headed with the numbers it is for ("Read the stimulus
 * below and answer questions 5 and 6"). The numbers are worked out every
 * time from where the questions stand, never written into a question.
 *
 * The one place this is decided, for every view of questions — the exam,
 * its result, the print, the picker's review, an import's preview — and
 * for both the question bank and past papers, whose MCQs share stimuli the
 * same way (Concerns\SharesStimulus).
 */
final class QuestionBlocks
{
    /**
     * The questions grouped into units — a lone question, or the questions
     * of one stimulus in their order — in the order the units first appear.
     * What an exam shuffles, so a group moves as one.
     *
     * @param  iterable<int, Model>  $questions
     * @return array<int, array<int, Model>>
     */
    public static function units(iterable $questions): array
    {
        $units = [];

        foreach ($questions as $question) {
            $units[self::groupKey($question)][] = $question;
        }

        return array_values(array_map(
            fn (array $unit): array => count($unit) > 1
                ? collect($unit)->sortBy(fn (Model $question): int => (int) $question->stimulus_order)->values()->all()
                : $unit,
            $units,
        ));
    }

    /**
     * The questions in paper order: each stimulus's questions together.
     *
     * @param  iterable<int, Model>  $questions
     * @return Collection<int, Model>
     */
    public static function arrange(iterable $questions): Collection
    {
        return collect(self::units($questions))->flatten(1)->values();
    }

    /**
     * The questions numbered in paper order, as blocks: each a run of the
     * questions under one stimulus (with that stimulus and its heading), or
     * a single question without one.
     *
     * @param  iterable<int, Model>  $questions
     * @return Collection<int, array{stimulus: Model|null, heading: string|null, questions: array<int, array{number: int, question: Model}>}>
     */
    public static function blocks(iterable $questions, int $firstNumber = 1): Collection
    {
        $number = $firstNumber;

        return collect(self::units($questions))->map(function (array $unit) use (&$number): array {
            $numbered = [];

            foreach ($unit as $question) {
                $numbered[] = ['number' => $number++, 'question' => $question];
            }

            $stimulus = self::stimulusOf($unit[0]);

            return [
                'stimulus' => $stimulus,
                'heading' => $stimulus ? self::heading(array_column($numbered, 'number')) : null,
                'questions' => $numbered,
            ];
        });
    }

    /**
     * "Read the stimulus below and answer question 5." / "… questions 5
     * and 6." / "… questions 5–7."
     *
     * @param  array<int, int>  $numbers
     */
    public static function heading(array $numbers): string
    {
        return match (count($numbers)) {
            1 => __('Read the stimulus below and answer question :number.', ['number' => $numbers[0]]),
            2 => __('Read the stimulus below and answer questions :first and :second.', ['first' => $numbers[0], 'second' => $numbers[1]]),
            default => __('Read the stimulus below and answer questions :first–:last.', ['first' => $numbers[0], 'last' => end($numbers)]),
        };
    }

    /**
     * The stimulus a question stands under — saved, or (in an import's
     * preview) only built.
     */
    public static function stimulusOf(Model $question): ?Model
    {
        if ($question->relationLoaded('stimulus')) {
            return $question->getRelation('stimulus');
        }

        return $question->getAttribute('stimulus_id') !== null ? $question->stimulus : null;
    }

    /**
     * What keeps the questions of one stimulus together: the stimulus (by
     * its id, or the very object while it is not saved yet), or the
     * question itself when it has none.
     */
    private static function groupKey(Model $question): string
    {
        $stimulusId = $question->getAttribute('stimulus_id');

        if ($stimulusId !== null) {
            return "stimulus-{$stimulusId}";
        }

        $stimulus = $question->relationLoaded('stimulus') ? $question->getRelation('stimulus') : null;

        return $stimulus ? 'new-stimulus-'.spl_object_id($stimulus) : 'question-'.spl_object_id($question);
    }
}

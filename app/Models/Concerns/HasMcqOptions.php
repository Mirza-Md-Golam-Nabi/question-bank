<?php

namespace App\Models\Concerns;

/**
 * What a student's pick of an MCQ option is recorded as, and what counts as
 * the right one — for a question-bank MCQ and a past paper's alike, so the
 * exam paper, the grading and the result all agree.
 *
 * A pick is the option's text, as it always was; an option that is only a
 * figure has no text, so its pick is the figure's path — still telling it
 * apart from the others, and never blank (a blank answer is "not answered").
 */
trait HasMcqOptions
{
    /**
     * @param  array{option?: ?string, image?: ?string}  $option
     */
    public static function answerValueOf(array $option): string
    {
        $text = (string) ($option['option'] ?? '');
        // An editor leaves "<p>&nbsp;</p>" behind in an option with no words.
        $hasText = preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';

        return ! $hasText && filled($option['image'] ?? null) ? (string) $option['image'] : $text;
    }

    /**
     * The pick that is right — null when no option is marked correct.
     */
    public function correctAnswerValue(): ?string
    {
        $correct = collect($this->options ?? [])->first(fn (array $option): bool => (bool) ($option['is_correct'] ?? false));

        return $correct === null ? null : static::answerValueOf($correct);
    }
}

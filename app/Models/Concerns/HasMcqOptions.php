<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

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

        return ! static::hasWords($text) && filled($option['image'] ?? null) ? (string) $option['image'] : $text;
    }

    /**
     * An explanation emptied in its editor ("<p></p>") is no explanation.
     */
    protected function explanation(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => static::hasWords((string) $value) || preg_match('/<(img|table)\b/i', (string) $value) ? $value : null,
        );
    }

    /**
     * An editor leaves "<p>&nbsp;</p>" behind when nothing is written.
     */
    private static function hasWords(string $html): bool
    {
        return preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';
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

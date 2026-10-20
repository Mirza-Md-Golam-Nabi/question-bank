<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * A question found to be already there (DuplicateQuestionFinder): which
 * one, how alike, and whether it is the very same question.
 */
final readonly class DuplicateMatch
{
    /**
     * @param  Model  $question  The question already there — not saved yet when it is earlier in the same JSON.
     * @param  int  $percent  How alike, 100 for the same question.
     * @param  string|null  $subject  Which of several questions being checked it was found for ("Question 2").
     */
    public function __construct(
        public Model $question,
        public int $percent,
        public bool $isExact,
        public ?string $subject = null,
    ) {}

    public function about(string $subject): self
    {
        return new self($this->question, $this->percent, $this->isExact, $subject);
    }
}

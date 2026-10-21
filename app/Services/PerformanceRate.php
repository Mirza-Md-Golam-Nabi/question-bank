<?php

namespace App\Services;

use App\Models\BillingSetting;

/**
 * How a group of MCQs went — a subject's, a chapter's, a difficulty's: how
 * many were on the papers, how many were answered correctly, how many left
 * blank. Its percentage is shown only once enough questions are behind it
 * (BillingSetting::performance_min_questions), so two questions never read
 * as "50% weak".
 */
final class PerformanceRate
{
    public function __construct(
        public readonly int $questions,
        public readonly int $correct,
        public readonly int $blank,
    ) {}

    /**
     * @param  object{questions: int|string, correct: int|string|null, blank: int|string|null}  $row  An aggregate row with those three columns.
     */
    public static function fromRow(object $row): self
    {
        return new self((int) $row->questions, (int) $row->correct, (int) $row->blank);
    }

    public function wrong(): int
    {
        return max(0, $this->questions - $this->correct - $this->blank);
    }

    public function hasEnoughData(): bool
    {
        return $this->questions >= BillingSetting::current()->performance_min_questions;
    }

    /**
     * The share answered correctly, in whole percent; null until there is
     * enough data to say.
     */
    public function percent(): ?int
    {
        return $this->hasEnoughData() ? (int) round($this->correct / $this->questions * 100) : null;
    }

    /**
     * The Filament colour a percentage is shown in, the same everywhere.
     */
    public static function colorFor(?int $percent): string
    {
        return match (true) {
            $percent === null => 'gray',
            $percent >= 75 => 'success',
            $percent >= 50 => 'warning',
            default => 'danger',
        };
    }
}

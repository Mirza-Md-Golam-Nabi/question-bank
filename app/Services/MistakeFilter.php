<?php

namespace App\Services;

use App\Enums\MistakeSource;

/**
 * How far a student narrows one section of their mistakes list: to the
 * question bank's or the past papers' mistakes, then a question-bank
 * chapter (and a topic in it), or a past paper year. A chapter or topic
 * means the question bank's mistakes only, and a year the past papers'
 * only — a past paper's MCQ has no chapter, a question-bank one no year.
 * What it means for the list is StudentMistakeTracker's business.
 */
final readonly class MistakeFilter
{
    public function __construct(
        public MistakeSource $source = MistakeSource::All,
        public ?int $chapterId = null,
        public ?int $topicId = null,
        public ?int $year = null,
    ) {}

    /**
     * The filter the mistakes page's form describes — a chapter or topic
     * only with the question bank chosen, a year only with past papers.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromForm(array $data): self
    {
        $source = MistakeSource::tryFrom((string) ($data['mistake_source'] ?? '')) ?? MistakeSource::All;
        $chapterId = $source === MistakeSource::QuestionBank && filled($data['chapter_id'] ?? null) ? (int) $data['chapter_id'] : null;

        return new self(
            source: $source,
            chapterId: $chapterId,
            topicId: $chapterId && filled($data['topic_id'] ?? null) ? (int) $data['topic_id'] : null,
            year: $source === MistakeSource::PastPapers && filled($data['year'] ?? null) ? (int) $data['year'] : null,
        );
    }

    public function includesQuestionBank(): bool
    {
        return $this->source !== MistakeSource::PastPapers && $this->year === null;
    }

    public function includesPastPapers(): bool
    {
        return $this->source !== MistakeSource::QuestionBank && $this->chapterId === null && $this->topicId === null;
    }
}

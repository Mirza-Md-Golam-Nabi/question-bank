<?php

namespace App\Filament\Support;

use App\Enums\CqPartType;
use Illuminate\Support\Arr;

/**
 * Moves a question's MCQ options and CQ sub-questions between how they are
 * stored and the shape the question form (QuestionFormSchema) edits them
 * in — the same for a question-bank question and a past paper's question,
 * so it lives here once.
 */
final class QuestionFormData
{
    /**
     * Stored options as the options Repeater shows them: each with its
     * "correct" tick and an "add image" box ticked when it has one.
     *
     * @param  array<int, array<string, mixed>>|null  $options
     * @return array<int, array<string, mixed>>
     */
    public static function fillOptions(?array $options): array
    {
        return collect($options ?? [])
            ->map(fn (array $option) => [
                ...$option,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'has_image' => filled($option['image'] ?? null),
            ])
            ->all();
    }

    /**
     * The form's options as stored: only `option`/`image`/`is_correct` (the
     * form's own rule guarantees exactly one is correct), without the
     * UI-only `has_image` flag.
     *
     * @param  array<int, array<string, mixed>>|null  $options
     * @return array<int, array<string, mixed>>
     */
    public static function storedOptions(?array $options): array
    {
        return collect($options ?? [])
            ->map(fn (array $option) => Arr::only($option, ['option', 'image', 'is_correct']))
            ->values()
            ->all();
    }

    /**
     * Stored sub-questions as the form's `cq_parts.{type}.*` fields.
     *
     * @param  iterable<int, object>  $parts  QuestionCqPart or BoardCqQuestionPart rows.
     * @return array<string, array<string, mixed>>
     */
    public static function fillCqParts(iterable $parts, bool $withAnswers = false): array
    {
        $data = [];

        foreach ($parts as $part) {
            $data[$part->part_type->value] = [
                'text' => $part->part_text,
                'image' => $part->part_image,
                'marks' => $part->marks,
                ...($withAnswers ? ['answer' => $part->answer] : []),
            ];
        }

        return $data;
    }

    /**
     * The form's `cq_parts` as rows ready to store, in part order — only
     * the sub-questions that have text. Null when the form had none.
     *
     * @param  array<string, array<string, mixed>>|null  $cqParts
     * @return array<int, array<string, mixed>>|null
     */
    public static function cqPartRows(?array $cqParts, bool $withAnswers = false): ?array
    {
        if (! $cqParts) {
            return null;
        }

        return collect(CqPartType::ordered())
            ->filter(fn (CqPartType $type) => filled($cqParts[$type->value]['text'] ?? null))
            ->map(fn (CqPartType $type) => [
                'part_type' => $type,
                'part_order' => $type->order(),
                'part_text' => $cqParts[$type->value]['text'],
                'part_image' => $cqParts[$type->value]['image'] ?? null,
                'marks' => $cqParts[$type->value]['marks'] ?? 1,
                ...($withAnswers ? ['answer' => $cqParts[$type->value]['answer'] ?? null] : []),
            ])
            ->values()
            ->all();
    }
}

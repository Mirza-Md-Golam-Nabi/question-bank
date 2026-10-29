<?php

namespace App\Models;

use App\Enums\CqPartType;
use App\Models\Concerns\SyncsMarksFromParts;
use App\Services\QuestionDuplicateScreening;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['question_id', 'part_type', 'part_order', 'part_text', 'part_image', 'marks'])]
class QuestionCqPart extends Model
{
    use HasFactory, SyncsMarksFromParts;

    /**
     * A CQ is answered by its parts: changing one has the question checked
     * for duplicates again.
     */
    protected static function booted(): void
    {
        static::saved(function (self $part): void {
            if ($part->wasRecentlyCreated || $part->wasChanged(['part_text', 'part_image'])) {
                $part->question && QuestionDuplicateScreening::queue($part->question);
            }
        });

        static::deleted(fn (self $part) => $part->question && QuestionDuplicateScreening::queue($part->question));
    }

    protected function casts(): array
    {
        return [
            'part_type' => CqPartType::class,
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    protected function parentForMarksSync(): ?Model
    {
        return $this->question;
    }

    protected function partsForMarksSync(): HasMany
    {
        return $this->question->cqParts();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['chapter_id', 'name', 'order_index'])]
class Topic extends Model
{
    use HasFactory, HasOrderIndex;

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * A chapter's topics in their order, as select options — none when no
     * chapter is given.
     *
     * @return array<int, string>
     */
    public static function optionsFor(int|string|null $chapterId): array
    {
        if (blank($chapterId)) {
            return [];
        }

        return self::query()->where('chapter_id', $chapterId)->ordered()->pluck('name', 'id')->all();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

#[Fillable(['academic_class_id', 'subject_id', 'order_index'])]
class ClassSubject extends Pivot
{
    use HasFactory, HasOrderIndex;

    public $incrementing = true;

    protected $table = 'class_subjects';

    /**
     * Turns figures worked out per class + subject (keyed by class_subject
     * id, e.g. a grouped count) into rows a table can show: each gets its
     * `class` and `subject` names, sorted by them. The names for all rows
     * come from one lookup.
     *
     * Kept per class_subject rather than per subject name on purpose: the
     * same subject is taught in several classes, each with its own
     * chapters, and merging them would mix those up.
     *
     * @param  Collection<int|string, array<string, mixed>>  $figuresByClassSubjectId
     * @return Collection<int, array<string, mixed>>
     */
    public static function describe(Collection $figuresByClassSubjectId): Collection
    {
        $classSubjects = self::query()
            ->with(['academicClass:id,name', 'subject:id,name'])
            ->findMany($figuresByClassSubjectId->keys())
            ->keyBy('id');

        return $figuresByClassSubjectId
            ->map(fn (array $figures, int|string $classSubjectId): array => [
                'class' => $classSubjects->get($classSubjectId)?->academicClass?->name ?? __('Unknown'),
                'subject' => $classSubjects->get($classSubjectId)?->subject?->name ?? __('Unknown'),
                ...$figures,
            ])
            ->sortBy(['class', 'subject'])
            ->values();
    }

    public function academicClass(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function chapters(): HasMany
    {
        // Explicit FK: Pivot::getForeignKey() (via AsPivot) returns the
        // belongsToMany "foreign key to parent" bookkeeping property, not
        // Eloquent's usual naming-convention guess, so hasMany() can't infer
        // the correct column here on its own.
        return $this->hasMany(Chapter::class, 'class_subject_id');
    }

    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(Question::class, Chapter::class, 'class_subject_id');
    }
}

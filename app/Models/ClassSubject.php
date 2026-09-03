<?php

namespace App\Models;

use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['academic_class_id', 'subject_id', 'order_index'])]
class ClassSubject extends Pivot
{
    use HasFactory, HasOrderIndex;

    public $incrementing = true;

    protected $table = 'class_subjects';

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

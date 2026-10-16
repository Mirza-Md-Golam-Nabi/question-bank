<?php

namespace App\Models;

use App\Models\Concerns\HasOrderIndex;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'order_index'])]
class AcademicClass extends Model
{
    use HasFactory, HasOrderIndex;

    /**
     * Classes as select options (name keyed by id), in display order — every
     * class, or only the given ones. The one place a class picker gets its
     * list from.
     *
     * @param  array<int, int>|null  $onlyIds
     * @return array<int, string>
     */
    public static function options(?array $onlyIds = null): array
    {
        return self::ordered()
            ->when($onlyIds !== null, fn ($query) => $query->whereKey($onlyIds))
            ->pluck('name', 'id')
            ->all();
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects')
            ->using(ClassSubject::class)
            ->withPivot('id', 'order_index')
            ->withTimestamps()
            ->orderByPivot('order_index');
    }
}

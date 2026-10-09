<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'name_bn', 'short_name'])]
class Subject extends Model
{
    use HasFactory;

    /**
     * The subject's name in the active language: the Bangla name when the
     * app is in Bangla and one has been entered, otherwise the English name.
     *
     * @return Attribute<string, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn (): string => app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name,
        );
    }

    /**
     * What a narrow column shows for the subject: its short name where one
     * has been entered, otherwise its name in the active language.
     *
     * @return Attribute<string, never>
     */
    protected function shortLabel(): Attribute
    {
        return Attribute::get(
            fn (): string => filled($this->short_name) ? $this->short_name : $this->display_name,
        );
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function academicClasses(): BelongsToMany
    {
        return $this->belongsToMany(AcademicClass::class, 'class_subjects')
            ->using(ClassSubject::class)
            ->withPivot('id', 'order_index')
            ->withTimestamps()
            ->orderByPivot('order_index');
    }
}

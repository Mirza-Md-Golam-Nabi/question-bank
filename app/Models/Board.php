<?php

namespace App\Models;

use App\Enums\PaperSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A source of past papers: an education board, or — by its `type` — a
 * medical or university admission test, a job exam, or a school/college's
 * test exam. Its papers are BoardQuestionPapers, a subsystem of their own
 * (CLAUDE.md rule 5).
 *
 * Named in English (`name`, `short_name`) and Bangla (`name_bn`,
 * `short_name_bn`); what is shown follows the active language.
 */
#[Fillable(['name', 'name_bn', 'short_name', 'short_name_bn', 'type'])]
class Board extends Model
{
    use HasFactory;

    protected $attributes = [
        'type' => 'board',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaperSourceType::class,
        ];
    }

    public function questionPapers(): HasMany
    {
        return $this->hasMany(BoardQuestionPaper::class);
    }

    /**
     * A to Z by what display_name shows, in the database.
     */
    public function scopeOrderedByDisplayName(Builder $query, string $direction = 'asc'): Builder
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return app()->getLocale() === 'bn'
            ? $query->orderByRaw("COALESCE(NULLIF(name_bn, ''), name) {$direction}")
            : $query->orderBy('name', $direction);
    }

    /**
     * The full name in the active language, the English one when no Bangla
     * one has been entered.
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(
            fn (): string => app()->getLocale() === 'bn' && filled($this->name_bn) ? $this->name_bn : $this->name,
        );
    }

    /**
     * The short name in the active language ("ঢাবি" / "DU") — what a
     * question shows to say where it came from.
     */
    protected function displayShortName(): Attribute
    {
        return Attribute::get(
            fn (): string => app()->getLocale() === 'bn' && filled($this->short_name_bn) ? $this->short_name_bn : $this->short_name,
        );
    }
}

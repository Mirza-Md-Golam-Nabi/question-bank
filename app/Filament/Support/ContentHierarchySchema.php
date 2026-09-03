<?php

namespace App\Filament\Support;

use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

/**
 * The Class → Subject (→ Chapter) cascading select, shared by every form
 * that needs to pin content to a class_subjects row: the Question form
 * (Admin/Teacher/Staff, which goes one level further to `chapter_id`) and
 * the Board Question Paper form (which stops at `class_subject_id`, since a
 * board paper isn't scoped to one chapter).
 */
class ContentHierarchySchema
{
    /**
     * @param  (Closure(?Model $record): int|null)|null  $resolveClassSubjectId
     *                                                                           How to read the current `class_subject_id` back off an
     *                                                                           existing record when hydrating the edit form — defaults to
     *                                                                           Question's `chapter->class_subject_id` path.
     * @param  bool  $dehydrateClassSubjectId  True when `class_subject_id`
     *                                         is itself the real persisted column (Board Question Paper);
     *                                         false when it's only a UI filter narrowing a deeper field like
     *                                         Question's `chapter_id`.
     * @return array<Component>
     */
    public static function classAndSubjectOnly(?Closure $resolveClassSubjectId = null, bool $dehydrateClassSubjectId = false): array
    {
        $resolveClassSubjectId ??= fn (?Model $record) => $record?->chapter?->class_subject_id;

        return [
            Select::make('academic_class_id')
                ->label('Class')
                ->options(fn () => AcademicClass::ordered()->pluck('name', 'id'))
                ->live()
                ->columns(1)
                ->dehydrated(false)
                ->afterStateHydrated(function (Select $component, ?Model $record, mixed $state) use ($resolveClassSubjectId) {
                    if (filled($state)) {
                        return;
                    }

                    $classSubjectId = $resolveClassSubjectId($record);
                    $component->state($classSubjectId ? ClassSubject::find($classSubjectId)?->academic_class_id : null);
                })
                ->afterStateUpdated(fn (Set $set) => $set('class_subject_id', null))
                ->required(),

            Select::make('class_subject_id')
                ->label('Subject')
                ->options(fn (Get $get) => ClassSubject::query()
                    ->where('academic_class_id', $get('academic_class_id'))
                    ->with('subject')
                    ->ordered()
                    ->get()
                    ->pluck('subject.name', 'id'))
                ->live()
                ->dehydrated($dehydrateClassSubjectId)
                ->afterStateHydrated(function (Select $component, ?Model $record, mixed $state) use ($resolveClassSubjectId) {
                    if (filled($state)) {
                        return;
                    }

                    $component->state($resolveClassSubjectId($record));
                })
                ->required(),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            ...static::classAndSubjectOnly(
                fn (?Model $record) => $record?->chapter?->class_subject_id,
                dehydrateClassSubjectId: false,
            ),

            Select::make('chapter_id')
                ->label('Chapter')
                ->options(fn (Get $get) => Chapter::query()
                    ->where('class_subject_id', $get('class_subject_id'))
                    ->ordered()
                    ->pluck('name', 'id'))
                ->required(),
        ];
    }
}

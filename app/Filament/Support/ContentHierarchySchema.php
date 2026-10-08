<?php

namespace App\Filament\Support;

use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Topic;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * The Class → Subject → Chapter → Topic cascading selects, in one place for
 * everything that walks that chain:
 *  - the Question form (Admin/Teacher/Staff), down to `chapter_id` and an
 *    optional `topic_id`;
 *  - the Board Question Paper form, which stops at `class_subject_id`
 *    (a board paper isn't scoped to one chapter);
 *  - the Teacher's question picker, which uses the four selects as filters.
 *
 * The four `*Select()` builders hold what never varies — the field name,
 * its label and the options each level offers given the level above. What a
 * form does on top of that (required or not, what to reset on change, how
 * to hydrate from a record) is added by the caller.
 */
class ContentHierarchySchema
{
    public static function classSelect(): Select
    {
        return Select::make('academic_class_id')
            ->label(__('Class'))
            ->options(fn () => AcademicClass::ordered()->pluck('name', 'id'))
            ->live();
    }

    public static function subjectSelect(): Select
    {
        return Select::make('class_subject_id')
            ->label(__('Subject'))
            ->options(fn (Get $get) => ClassSubject::query()
                ->where('academic_class_id', $get('academic_class_id'))
                ->with('subject')
                ->ordered()
                ->get()
                ->pluck('subject.name', 'id'))
            ->live();
    }

    public static function chapterSelect(): Select
    {
        return Select::make('chapter_id')
            ->label(__('Chapter'))
            ->options(fn (Get $get) => Chapter::query()
                ->where('class_subject_id', $get('class_subject_id'))
                ->ordered()
                ->pluck('name', 'id'))
            ->live();
    }

    public static function topicSelect(): Select
    {
        return Select::make('topic_id')
            ->label(__('Topic'))
            ->options(fn (Get $get) => Topic::query()
                ->where('chapter_id', $get('chapter_id'))
                ->ordered()
                ->pluck('name', 'id'));
    }

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
            static::classSelect()
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

            static::subjectSelect()
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
     * The full chain as the Question form uses it.
     *
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            ...static::classAndSubjectOnly(
                fn (?Model $record) => $record?->chapter?->class_subject_id,
                dehydrateClassSubjectId: false,
            ),

            static::chapterSelect()
                // A topic only makes sense within its own chapter, so switching
                // chapter swaps it for the one this user last used there (if any).
                ->afterStateUpdated(fn (Set $set, mixed $state) => $set('topic_id', TopicPreference::for(auth()->user(), $state)))
                ->required(),

            static::topicSelect()
                ->placeholder(__('No topic'))
                ->rule(fn (Get $get) => Rule::exists(Topic::class, 'id')->where('chapter_id', $get('chapter_id'))),
        ];
    }
}

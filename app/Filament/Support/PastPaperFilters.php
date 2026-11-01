<?php

namespace App\Filament\Support;

use App\Enums\PaperSourceType;
use App\Models\AcademicClass;
use App\Models\Board;
use App\Models\ClassSubject;
use App\Models\Subject;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kind → Source and Class → Subject, and Year: how every past papers table
 * narrows its papers. Picking a kind or a class shortens the list below it,
 * and changes nothing about what the lower filter does on its own.
 *
 * Each filter is a subquery on a column of the paper, already indexed, so
 * the list's own query stays one query however many filters are on.
 */
class PastPaperFilters
{
    /**
     * @return array<int, SelectFilter>
     */
    public static function all(): array
    {
        return [
            self::kind(),
            self::source(),
            self::academicClass(),
            self::subject(),
            self::year(),
        ];
    }

    private static function kind(): SelectFilter
    {
        return SelectFilter::make('source_type')
            ->label(__('Kind'))
            ->options(PaperSourceType::class)
            ->modifyFormFieldUsing(fn (Select $field): Select => $field
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('../board_id.value', null)))
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, string $type) => $query->whereIn('board_id', Board::query()->where('type', $type)->select('id')),
            ));
    }

    private static function source(): SelectFilter
    {
        return SelectFilter::make('board_id')
            ->label(__('Source'))
            ->options(fn (): array => Board::options())
            ->searchable()
            ->preload()
            ->modifyFormFieldUsing(fn (Select $field): Select => $field
                ->options(fn (Get $get): array => Board::options($get('../source_type.value'))));
    }

    private static function academicClass(): SelectFilter
    {
        return SelectFilter::make('class')
            ->label(__('Class'))
            ->options(fn (): array => AcademicClass::options())
            ->modifyFormFieldUsing(fn (Select $field): Select => $field
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('../subject.value', null)))
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, string $classId) => $query->whereIn('class_subject_id', ClassSubject::query()->where('academic_class_id', $classId)->select('id')),
            ));
    }

    /**
     * By subject, not by one class's subject, so "Physics" alone finds its
     * papers of every class.
     */
    private static function subject(): SelectFilter
    {
        return SelectFilter::make('subject')
            ->label(__('Subject'))
            ->options(fn (): array => Subject::options())
            ->searchable()
            ->preload()
            ->modifyFormFieldUsing(fn (Select $field): Select => $field
                ->options(fn (Get $get): array => Subject::options($get('../class.value'))))
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $query, string $subjectId) => $query->whereIn('class_subject_id', ClassSubject::query()->where('subject_id', $subjectId)->select('id')),
            ));
    }

    /**
     * The years of the papers this table can list, so a Staff member is
     * offered only the years of their own papers and a student only those of
     * approved ones.
     */
    private static function year(): SelectFilter
    {
        return SelectFilter::make('year')
            ->label(__('Year'))
            ->options(fn (Table $table): array => $table->getQuery()
                ->reorder()
                ->select('year')
                ->distinct()
                ->orderByDesc('year')
                ->pluck('year')
                ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                ->all());
    }
}

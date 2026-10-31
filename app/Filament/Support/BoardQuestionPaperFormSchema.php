<?php

namespace App\Filament\Support;

use App\Enums\PaperSourceType;
use App\Models\Board;
use App\Models\BoardQuestionPaper;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;

/**
 * A past paper's details: where it is from (a board, a medical college, a
 * university…), the year and an optional unit, the class + subject for a
 * board's or a school's paper only, and how it is sat. Its questions are
 * not here: they are added one by one on the paper's page
 * (PaperMcqQuestionsRelationManager / PaperCqQuestionsRelationManager), so
 * a paper of a hundred questions is never one huge form.
 *
 * The same form for the Admin and a Staff member.
 */
class BoardQuestionPaperFormSchema
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Section::make(__('Paper'))
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        // Only narrows the sources below; the paper keeps just its source.
                        Select::make('source_type')
                            ->label(__('Kind'))
                            ->options(PaperSourceType::class)
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (Select $component, ?BoardQuestionPaper $record) => $component->state($record?->board?->type))
                            ->afterStateUpdated(fn (Set $set) => $set('board_id', null))
                            ->live(),
                        Select::make('board_id')
                            ->label(__('Source'))
                            ->options(fn (Get $get): array => Board::orderedByDisplayName()
                                ->when($get('source_type'), fn (Builder $query, PaperSourceType $type) => $query->where('type', $type))
                                ->get()
                                ->mapWithKeys(fn (Board $board): array => [$board->id => "{$board->display_name} ({$board->display_short_name})"])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        TextInput::make('year')
                            ->numeric()
                            ->integer()
                            ->minValue(1970)
                            ->maxValue((int) date('Y'))
                            ->required()
                            ->rule(fn (Get $get, ?BoardQuestionPaper $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $boardId = (int) $get('board_id');

                                if (! $boardId || ! is_numeric($value)) {
                                    return;
                                }

                                $classSubjectId = self::hasClassSubject($get) ? ((int) $get('class_subject_id') ?: null) : null;

                                if (BoardQuestionPaper::isTaken($boardId, $classSubjectId, (int) $value, $get('unit'), $record?->id)) {
                                    $fail(__('This paper has already been entered.'));
                                }
                            }),
                        TextInput::make('unit')
                            ->label(__('Unit / set (optional)'))
                            ->helperText(__('For example a university unit such as "Ka". Leave empty if the test has none.'))
                            ->maxLength(50)
                            ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
                    ]),

                    Grid::make(2)
                        ->visible(fn (Get $get): bool => self::hasClassSubject($get))
                        ->schema(ContentHierarchySchema::classAndSubjectOnly(
                            resolveClassSubjectId: fn (?BoardQuestionPaper $record) => $record?->class_subject_id,
                            dehydrateClassSubjectId: true,
                        )),

                    Grid::make(2)->schema([
                        TextInput::make('duration_minutes')
                            ->label(__('Time (minutes)'))
                            ->helperText(__('How long an exam of this paper lasts. Leave empty for a minute per MCQ.'))
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        TextInput::make('negative_marks')
                            ->label(__('Marks lost per wrong MCQ answer'))
                            ->helperText(__('As in the real test, e.g. 0.25. Leave empty if it had no negative marking.'))
                            ->numeric()
                            ->step(0.05)
                            ->minValue(0),
                    ]),
                ]),
        ];
    }

    /**
     * Whether the chosen source's papers are for one class + subject (a
     * board's or a school's) — an admission test's are not.
     */
    public static function hasClassSubject(Get $get): bool
    {
        return self::sourceTypeOf($get('board_id'))?->hasClassSubject() ?? false;
    }

    /**
     * What the form saves, made consistent: no class + subject on an
     * admission paper (the fields are hidden, so they may hold an old pick).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (! (self::sourceTypeOf($data['board_id'] ?? null)?->hasClassSubject() ?? false)) {
            $data['class_subject_id'] = null;
        }

        $data['unit'] = trim((string) ($data['unit'] ?? ''));

        return $data;
    }

    /**
     * A source's kind — every source's read once per request.
     */
    private static function sourceTypeOf(mixed $boardId): ?PaperSourceType
    {
        $types = once(fn (): array => Board::query()->get(['id', 'type'])->pluck('type', 'id')->all());

        return is_numeric($boardId) ? ($types[(int) $boardId] ?? null) : null;
    }
}

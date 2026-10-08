<?php

namespace App\Filament\Support;

use App\Enums\CqPartType;
use App\Models\Board;
use App\Models\BoardQuestionPaper;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The Board Question Paper create/edit form. Unlike the regular question
 * bank, a whole paper is approved/rejected as one unit (no per-question
 * versioning), so its MCQ/CQ questions are plain array fields synced
 * explicitly by the page (see HandlesBoardQuestionPaperForm) rather than
 * Filament's own ->relationship() repeater saving.
 */
class BoardQuestionPaperFormSchema
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(3)->schema([
                Select::make('board_id')
                    ->label(__('Board'))
                    ->options(fn () => Board::ordered()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('year')
                    ->numeric()
                    ->minValue(1990)
                    ->maxValue((int) date('Y'))
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, Get $get) => $rule
                            ->where('board_id', $get('board_id'))
                            ->where('class_subject_id', $get('class_subject_id')),
                    ),
            ]),

            ...ContentHierarchySchema::classAndSubjectOnly(
                resolveClassSubjectId: fn (?BoardQuestionPaper $record) => $record?->class_subject_id,
                dehydrateClassSubjectId: true,
            ),

            Tabs::make('Questions')->tabs([
                Tabs\Tab::make(__('MCQ'))->schema([
                    Repeater::make('mcq_questions')
                        ->label(__('MCQ questions'))
                        ->schema(static::mcqQuestionComponents())
                        ->addActionLabel(__('Add MCQ question'))
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => strip_tags($state['question_text'] ?? '') ?: null),
                ]),
                Tabs\Tab::make(__('CQ'))->schema([
                    Repeater::make('cq_questions')
                        ->label(__('CQ questions'))
                        ->schema(static::cqQuestionComponents())
                        ->addActionLabel(__('Add CQ question'))
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => strip_tags($state['question_text'] ?? '') ?: null),
                ]),
            ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function mcqQuestionComponents(): array
    {
        return [
            RichEditor::make('question_text')->label(__('Question text'))->required(),
            FileUpload::make('question_image')->label(__('Image (optional)'))->image()->disk('public')->visibility('public')->directory('board-mcq-questions'),
            Repeater::make('options')
                ->schema(static::mcqOptionComponents())
                ->columns(2)
                ->grid(4)
                ->minItems(2)
                ->maxItems(6)
                ->required()
                ->rule(McqOptionsSchema::exactlyOneCorrectOptionRule()),
            TextInput::make('marks')->numeric()->step(0.5)->minValue(0.5)->default(1)->required(),
        ];
    }

    /**
     * @return array<Component>
     */
    protected static function mcqOptionComponents(): array
    {
        return McqOptionsSchema::components(
            TextInput::make('option')
                ->label(__('Option'))
                ->required()
                ->columnSpanFull(),
            imageDirectory: 'board-mcq-options',
        );
    }

    /**
     * @return array<Component>
     */
    protected static function cqQuestionComponents(): array
    {
        return [
            RichEditor::make('question_text')->label(__('Stimulus'))->required(),
            FileUpload::make('question_image')->label(__('Image (optional)'))->image()->disk('public')->visibility('public')->directory('board-cq-questions'),
            ...collect(CqPartType::ordered())
                ->map(fn (CqPartType $partType) => Section::make($partType->getLabel())
                    ->schema([
                        RichEditor::make("cq_parts.{$partType->value}.text")->label(__('Sub-question text'))->required(),
                        TextInput::make("cq_parts.{$partType->value}.marks")->label(__('Marks'))->numeric()->step(0.5)->minValue(0.5)->default(1)->required(),
                    ])
                    ->columns(2))
                ->all(),
        ];
    }
}

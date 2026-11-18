<?php

namespace App\Filament\Teacher\Resources\OmrChecks\Schemas;

use App\Enums\Locale;
use App\Enums\OmrIdentity;
use App\Filament\Support\NegativeMarkingFields;
use App\Models\AnswerKeyQuestion;
use App\Models\Exam;
use App\Services\OmrCheckService;
use App\Support\OmrSheet;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The form of an OMR check (CLAUDE.md rule 10.12) — making one and changing
 * its answer key: its title, how many questions and options, the marks of
 * a right answer, negative marking (off to begin with), which number tells
 * the students apart, and each question's right option, picked on a grid
 * of circles (filament.support.omr-answer-key-field).
 */
class OmrCheckForm
{
    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            TextInput::make('title')
                ->label(__('Title'))
                ->required()
                ->maxLength(255),
            Grid::make(['default' => 1, 'sm' => 3])
                ->schema([
                    TextInput::make('questions')
                        ->label(__('Number of questions'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(OmrSheet::MAX_QUESTIONS)
                        ->required()
                        ->live(onBlur: true),
                    Select::make('options')
                        ->label(__('Options per question'))
                        ->options(collect(range(OmrCheckService::MIN_OPTIONS, count(AnswerKeyQuestion::OPTION_VALUES)))
                            ->mapWithKeys(fn (int $count): array => [$count => Locale::current()->writeDigits((string) $count)])
                            ->all())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    TextInput::make('marks')
                        ->label(__('Marks per right answer'))
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue(100)
                        ->step(0.01)
                        ->required(),
                ])
                ->columnSpanFull(),
            Radio::make('omr_identity')
                ->label(__('Save each result by'))
                ->helperText(__('The number the scanner reads off each sheet to tell the students apart. A School sends the results to its software by the same number.'))
                ->options(OmrIdentity::class)
                ->inline()
                ->required(),
            ...NegativeMarkingFields::make(helperText: __('Marks are taken off for each wrong answer. A blank answer loses nothing, and the total never goes below 0.')),
            ViewField::make('answers')
                ->label(__('Answer key'))
                ->view('filament.support.omr-answer-key-field')
                ->viewData(fn (Get $get): array => [
                    'questionCount' => max(0, min(OmrSheet::MAX_QUESTIONS, (int) $get('questions'))),
                    'optionCount' => max(OmrCheckService::MIN_OPTIONS, min(count(AnswerKeyQuestion::OPTION_VALUES), (int) $get('options'))),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * The form's starting state: a new check's defaults — four options, 1
     * mark, no negative marking, by roll — or the exam's own.
     *
     * @return array<string, mixed>
     */
    public static function fill(?Exam $exam = null): array
    {
        $key = $exam?->answerKey();

        return [
            'title' => $exam?->title,
            'questions' => $key ? count($key['answers']) : null,
            'options' => $key['options'] ?? 4,
            'marks' => $key['marks'] ?? 1,
            'omr_identity' => $exam?->omr_identity ?? OmrIdentity::Roll,
            ...NegativeMarkingFields::fill($exam),
            'answers' => $key['answers'] ?? [],
        ];
    }

    /**
     * What the form gives OmrCheckService::save().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function dataFor(array $data): array
    {
        return [
            ...$data,
            'negative_marks' => NegativeMarkingFields::valueFrom($data),
            'answers' => array_values(array_slice((array) ($data['answers'] ?? []), 0, (int) ($data['questions'] ?? 0))),
        ];
    }
}

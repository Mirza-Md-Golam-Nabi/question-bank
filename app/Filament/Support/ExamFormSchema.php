<?php

namespace App\Filament\Support;

use App\Models\Question;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;

class ExamFormSchema
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('title')->required(),

            Grid::make(3)->schema([
                Select::make('subject_id')
                    ->label(__('Subject'))
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->afterStateUpdated(fn ($set) => $set('questions', [])),
                TextInput::make('duration_minutes')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                DateTimePicker::make('start_time'),
            ]),

            Select::make('questions')
                // The relationship itself is limited to the subject's approved
                // pool and searched on the server a short list at a time —
                // never preloaded, since a subject has thousands of questions.
                ->relationship(
                    'questions',
                    'question_text',
                    fn (Builder $query, Get $get) => $query->approvedPool()->ofSubject($get('subject_id') ?? 0),
                )
                ->getOptionLabelFromRecordUsing(fn (Question $record) => strip_tags($record->question_text))
                ->optionsLimit(Question::SELECT_OPTIONS_LIMIT)
                ->multiple()
                ->searchable()
                ->required(),
        ];
    }
}

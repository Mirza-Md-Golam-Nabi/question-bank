<?php

namespace App\Filament\Support;

use App\Models\Question;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

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
                ->relationship('questions', 'question_text')
                ->getOptionLabelFromRecordUsing(fn (Question $record) => strip_tags($record->question_text))
                ->options(fn (Get $get) => Question::approvedOptionsForSubject($get('subject_id')))
                ->multiple()
                ->searchable()
                ->preload()
                ->required(),
        ];
    }
}

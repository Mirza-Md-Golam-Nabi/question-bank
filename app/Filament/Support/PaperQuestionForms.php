<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

/**
 * The forms a past paper's MCQ and CQ are written in — built from the very
 * pieces of the question-bank form (QuestionFormSchema: the editor choice,
 * text, image and preview, the options, the sub-questions), without the
 * class/subject/chapter/topic a paper's question has no use for. A CQ's
 * sub-questions also take an optional answer each.
 */
class PaperQuestionForms
{
    /**
     * @return array<Component>
     */
    public static function mcq(): array
    {
        return [
            Grid::make(4)->columnSpanFull()->schema([
                QuestionFormSchema::textImageAndPreview('board-mcq-questions', __('Question text'))->columnSpan(2),

                Group::make()
                    ->columnSpan(2)
                    ->schema([
                        QuestionFormSchema::editorModeRadio(),
                        TextInput::make('marks')
                            ->numeric()
                            ->step(0.5)
                            ->minValue(0.5)
                            ->default(1)
                            ->required(),
                    ]),
            ]),

            Group::make()
                ->columnSpanFull()
                ->schema(QuestionFormSchema::mcqComponents('board-mcq-options')),
        ];
    }

    /**
     * @return array<Component>
     */
    public static function cq(): array
    {
        return [
            Grid::make(4)->columnSpanFull()->schema([
                QuestionFormSchema::textImageAndPreview('board-cq-questions', __('Stimulus'))->columnSpan(2),

                Group::make()
                    ->columnSpan(2)
                    ->schema([QuestionFormSchema::editorModeRadio()]),
            ]),

            Group::make()
                ->columnSpanFull()
                ->schema(QuestionFormSchema::cqComponents('board-cq-parts', withAnswers: true)),
        ];
    }
}

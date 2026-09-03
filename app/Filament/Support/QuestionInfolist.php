<?php

namespace App\Filament\Support;

use App\Enums\EditorMode;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Question;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

/**
 * Read-only display for the "View" row action on every panel's Questions
 * table — shows the resolved chain (class/subject/chapter/topic), which
 * editor authored the question, the rendered question text/image, and the
 * MCQ options or CQ parts depending on question_type.
 */
class QuestionInfolist
{
    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(4)->schema([
                TextEntry::make('chapter.classSubject.academicClass.name')->label('Class'),
                TextEntry::make('chapter.classSubject.subject.name')->label('Subject'),
                TextEntry::make('chapter.name')->label('Chapter'),
                TextEntry::make('topic.name')->label('Topic')->placeholder('—'),

                TextEntry::make('question_type')->label('Type')->badge(),
                TextEntry::make('difficulty')->badge(),
                TextEntry::make('marks'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn (QuestionStatus $state) => $state->getColor()),

                TextEntry::make('editor_mode')
                    ->label('Editor')
                    ->badge()
                    ->color(fn (EditorMode $state) => $state === EditorMode::CkEditor ? 'warning' : 'info'),
            ]),

            TextEntry::make('question_text')
                ->label(fn (Question $record) => $record->question_type === QuestionType::Cq ? 'উদ্দীপক (Stimulus)' : 'Question text')
                ->html()
                ->columnSpanFull(),

            ImageEntry::make('question_image')
                ->hiddenLabel()
                ->visible(fn (Question $record) => filled($record->question_image))
                ->columnSpanFull(),

            Section::make('Options')
                ->visible(fn (Question $record) => $record->question_type === QuestionType::Mcq)
                ->schema([
                    RepeatableEntry::make('options')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('option')->hiddenLabel(),
                        ])
                        ->grid(4)
                        ->columnSpanFull(),

                    TextEntry::make('correct_answer')
                        ->label('Correct answer')
                        ->badge()
                        ->color('success'),
                ]),

            Section::make('CQ Parts')
                ->visible(fn (Question $record) => $record->question_type === QuestionType::Cq)
                ->schema([
                    RepeatableEntry::make('cqParts')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('part_type')->label('Part')->badge(),
                            TextEntry::make('part_text')->hiddenLabel()->html(),
                            ImageEntry::make('part_image')
                                ->hiddenLabel()
                                ->visible(fn ($record) => filled($record->part_image)),
                            TextEntry::make('marks')->label('Marks'),
                        ]),
                ]),
        ];
    }
}

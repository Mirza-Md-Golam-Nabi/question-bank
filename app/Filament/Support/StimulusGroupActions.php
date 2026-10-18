<?php

namespace App\Filament\Support;

use App\Enums\Difficulty;
use App\Models\Chapter;
use App\Models\Question;
use App\Services\StimulusGroupService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;

/**
 * The question bank's buttons for a stimulus with MCQs: "New stimulus with
 * MCQs" on a chapter's question list, and "Edit stimulus" on a question of
 * a group (whose own edit button gives way to it, so the group is always
 * changed as one). The window is StimulusGroupForm's; saving is
 * StimulusGroupService's.
 */
class StimulusGroupActions
{
    public static function create(Chapter $chapter): Action
    {
        return StimulusGroupForm::configureAction(
            Action::make('createStimulusGroup'),
            creating: true,
            imageDirectory: 'questions',
            groupFields: static::groupFields($chapter->id),
            questionFields: static::questionFields(),
        )
            ->color('gray')
            ->visible(fn (): bool => (bool) auth()->user()?->can('create', Question::class))
            // No fillForm() here: the fields' own defaults (the chapter, the
            // last topic, two empty questions, the user's editor) apply.
            ->action(function (array $data) use ($chapter): void {
                app(StimulusGroupService::class)->create($chapter, $data);

                TopicPreference::remember(auth()->user(), $chapter->id, $data['topic_id'] ?? null);

                Notification::make()->title(__('Stimulus and questions saved'))->success()->send();
            });
    }

    public static function edit(): Action
    {
        return StimulusGroupForm::configureAction(
            Action::make('editStimulusGroup'),
            creating: false,
            imageDirectory: 'questions',
            groupFields: static::groupFields(),
            questionFields: static::questionFields(),
        )
            ->iconButton()
            ->tooltip(__('Edit stimulus'))
            ->visible(fn (Question $record): bool => $record->isInStimulusGroup() && $record->is_latest && auth()->user()->can('update', $record))
            ->fillForm(fn (Question $record): array => [
                ...StimulusGroupForm::fill(
                    $record->stimulus,
                    app(StimulusGroupService::class)->currentQuestions($record->stimulus),
                    StimulusGroupService::QUESTION_FIELDS,
                ),
                'chapter_id' => $record->chapter_id,
                'topic_id' => $record->topic_id,
            ])
            ->action(function (Question $record, array $data): void {
                app(StimulusGroupService::class)->update($record->stimulus, $data);

                Notification::make()->title(__('Stimulus and questions saved'))->success()->send();
            });
    }

    /**
     * The topic the whole group is filed under (its select lists the
     * topics of the chapter the form names).
     *
     * @return array<Component>
     */
    private static function groupFields(?int $chapterId = null): array
    {
        return [
            Hidden::make('chapter_id')->default($chapterId),
            ContentHierarchySchema::topicSelect()
                ->default(fn () => $chapterId ? TopicPreference::for(auth()->user(), $chapterId) : null),
        ];
    }

    /**
     * @return array<Component>
     */
    private static function questionFields(): array
    {
        return [
            Select::make('difficulty')
                ->options(Difficulty::class)
                ->default(Difficulty::Easy)
                ->required()
                ->native(false),
        ];
    }
}

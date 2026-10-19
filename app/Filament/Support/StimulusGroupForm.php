<?php

namespace App\Filament\Support;

use App\Enums\EditorMode;
use App\Filament\Forms\Components\CkEditorField;
use App\Models\Question;
use App\Models\QuestionStimulus;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The form a shared stimulus is written in with its MCQs — "read the
 * stimulus and answer the questions below" — the same in the question
 * bank and on a past paper. The stimulus is written like a question's
 * text (QuestionFormSchema: the editor choice, text, image, preview); each
 * MCQ under it in CKEditor, with its options, explanation, marks and
 * whatever its side adds (a difficulty in the question bank).
 *
 * The form keeps the stimulus under the question form's own field names
 * (`question_text`, `question_image`, `editor_mode`) and the MCQs in
 * `questions`; stimulus() and questionRows() turn that into what is stored.
 */
class StimulusGroupForm
{
    /**
     * @param  array<Component>  $groupFields  Fields for the whole group (the question bank's topic).
     * @param  array<Component>  $questionFields  Fields each MCQ adds (the question bank's difficulty).
     * @return array<Component>
     */
    public static function components(string $imageDirectory, array $groupFields = [], array $questionFields = []): array
    {
        return [
            Grid::make(4)->columnSpanFull()->schema([
                QuestionFormSchema::textImageAndPreview("{$imageDirectory}-stimuli", __('Stimulus'))->columnSpan(2),

                Group::make()
                    ->columnSpan(2)
                    ->schema([QuestionFormSchema::editorModeRadio(), ...$groupFields]),
            ]),

            Repeater::make('questions')
                ->label(__('Questions on this stimulus'))
                ->schema([
                    // Which stored question an item is, when editing.
                    Hidden::make('id'),

                    CkEditorField::make('question_text')
                        ->label(__('Question text'))
                        ->required()
                        ->columnSpanFull(),

                    ...QuestionFormSchema::mcqComponents("{$imageDirectory}-options"),

                    Grid::make(2)->schema([
                        TextInput::make('marks')
                            ->numeric()
                            ->step(0.5)
                            ->minValue(0.5)
                            ->default(Question::DEFAULT_MCQ_MARKS)
                            ->required(),
                        ...$questionFields,
                    ]),
                ])
                ->addActionLabel(__('Add a question'))
                ->defaultItems(QuestionStimulus::MIN_QUESTIONS)
                ->minItems(QuestionStimulus::MIN_QUESTIONS)
                ->maxItems(QuestionStimulus::MAX_QUESTIONS)
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => strip_tags((string) ($state['question_text'] ?? '')) ?: null)
                ->columnSpanFull(),
        ];
    }

    /**
     * The window a group is written in, for an action of either side:
     * "new stimulus" or "edit stimulus", with the side's own fields.
     *
     * @param  array<Component>  $groupFields
     * @param  array<Component>  $questionFields
     */
    public static function configureAction(Action $action, bool $creating, string $imageDirectory, array $groupFields = [], array $questionFields = []): Action
    {
        return $action
            ->label($creating ? __('New stimulus with MCQs') : __('Edit stimulus'))
            ->icon($creating ? Heroicon::OutlinedQueueList : Heroicon::OutlinedPencilSquare)
            ->modalHeading($creating ? __('New stimulus with MCQs') : __('Edit stimulus and its questions'))
            ->modalDescription(__('Write the stimulus once, then the questions that use it. The paper heads them with their numbers itself ("Read the stimulus below and answer questions 5 and 6").'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel(__('Save'))
            ->schema(static::components($imageDirectory, $groupFields, $questionFields));
    }

    /**
     * A saved group as the form shows it.
     *
     * @param  Collection<int, Model>  $questions  The group's questions, in order.
     * @param  array<int, string>  $questionFields  The names of the fields each question adds.
     * @return array<string, mixed>
     */
    public static function fill(Model $stimulus, Collection $questions, array $questionFields = []): array
    {
        return [
            'question_text' => $stimulus->text,
            'question_image' => $stimulus->image,
            'editor_mode' => $stimulus->editor_mode,
            'questions' => $questions->map(fn (Model $question): array => [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'options' => QuestionFormData::fillOptions($question->options),
                'explanation' => $question->explanation,
                'marks' => $question->marks,
                ...Arr::only($question->getAttributes(), $questionFields),
            ])->values()->all(),
        ];
    }

    /**
     * The stimulus's own columns from the form's state.
     *
     * @param  array<string, mixed>  $data
     * @return array{text: string, image: string|null, editor_mode: mixed}
     */
    public static function stimulus(array $data): array
    {
        return [
            'text' => $data['question_text'],
            'image' => $data['question_image'] ?? null,
            'editor_mode' => $data['editor_mode'],
        ];
    }

    /**
     * Each MCQ of the form as the columns it is stored with, in order —
     * keyed by `id` for one already stored (0 for a new one is left out).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $questionFields  The names of the fields each question adds.
     * @return array<int, array{id: int|null, attributes: array<string, mixed>}>
     */
    public static function questionRows(array $data, array $questionFields = []): array
    {
        return collect($data['questions'] ?? [])
            ->values()
            ->map(fn (array $item, int $index): array => [
                'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                'attributes' => [
                    'question_text' => $item['question_text'],
                    // The questions are written in CKEditor (with its maths).
                    'editor_mode' => EditorMode::CkEditor,
                    'options' => QuestionFormData::storedOptions($item['options'] ?? []),
                    'explanation' => $item['explanation'] ?? null,
                    'marks' => $item['marks'] ?? Question::DEFAULT_MCQ_MARKS,
                    'stimulus_order' => $index + 1,
                    ...Arr::only($item, $questionFields),
                ],
            ])
            ->all();
    }
}

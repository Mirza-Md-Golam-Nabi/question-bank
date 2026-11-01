<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/**
 * The fields of one MCQ option inside an `options` Repeater, and the rule
 * for which of them are marked correct — the same for a question in the
 * question bank and for an MCQ on a board question paper. In the question
 * bank exactly one is; a past paper's MCQ is written as printed
 * (`$asPrinted`), so it may have several, or none when noCorrectAnswerField()
 * says so. Otherwise the two only differ in how the option text is typed and
 * where its image is stored.
 */
class McqOptionsSchema
{
    /**
     * @param  Component  $optionField  The `option` text field itself (a rich editor for question-bank questions, a plain input on board papers).
     * @return array<Component>
     */
    public static function components(Component $optionField, string $imageDirectory, bool $asPrinted = false): array
    {
        return [
            $optionField,

            Checkbox::make('is_correct')
                ->label(__('Correct answer'))
                ->live()
                ->hidden(fn (Get $get): bool => $asPrinted && (bool) $get('../../no_correct_answer'))
                // Ticking one option unticks every other one of the question.
                ->afterStateUpdated(function (bool $state, Checkbox $component, Get $get, Set $set) use ($asPrinted) {
                    if (! $state || $asPrinted) {
                        return;
                    }

                    $currentItemKey = (string) Str::of($component->getStatePath())
                        ->beforeLast('.is_correct')
                        ->afterLast('.');

                    self::untick($get('../'), $set, '../', exceptItemKey: $currentItemKey);
                }),

            Checkbox::make('has_image')
                ->label(__('Add image'))
                ->live()
                ->dehydrated(false),

            FileUpload::make('image')
                ->label(__('Image'))
                ->image()
                ->disk('public')->visibility('public')
                ->directory($imageDirectory)
                ->visible(fn (Get $get) => (bool) $get('has_image'))
                ->columnSpanFull(),
        ];
    }

    /**
     * A past paper's MCQ whose paper says none of its options is right —
     * ticking it clears the options' ticks. Not stored: no option being
     * correct is what says so (QuestionFormData::fillAnswers()).
     */
    public static function noCorrectAnswerField(): Checkbox
    {
        return Checkbox::make('no_correct_answer')
            ->label(__('This question has no correct answer'))
            ->helperText(__('Only when the paper or its answer key says so. When it gives several correct answers, tick all of them instead.'))
            ->live()
            ->dehydrated(false)
            ->afterStateUpdated(function (bool $state, Get $get, Set $set): void {
                if (! $state) {
                    return;
                }

                self::untick($get('options'), $set, 'options.');
            });
    }

    /**
     * Clears the tick of these options. A plain loop: Set hands back the
     * value it was given, and a false from it would end a collection's each().
     *
     * @param  array<int|string, mixed>|null  $options  The options Repeater's items.
     */
    private static function untick(?array $options, Set $set, string $pathToItems, ?string $exceptItemKey = null): void
    {
        foreach (array_keys($options ?? []) as $itemKey) {
            if ((string) $itemKey !== $exceptItemKey) {
                $set("{$pathToItems}{$itemKey}.is_correct", false);
            }
        }
    }

    public static function correctOptionRule(bool $asPrinted = false): Closure
    {
        // Filament evaluates whatever ->rule() is given through its own
        // closure-injection first (resolving parameters by name), which
        // can't resolve Laravel's $attribute/$value/$fail — so this outer
        // closure is what Filament calls, and its return value (the real
        // validator closure) is what Laravel then runs.
        return fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($asPrinted, $get) {
            if ($asPrinted && $get('no_correct_answer')) {
                return;
            }

            if (collect($value)->contains(fn ($option) => (bool) ($option['is_correct'] ?? false))) {
                return;
            }

            $fail($asPrinted
                ? __('Mark the correct answer, or tick that this question has none.')
                : __('Mark one option as the correct answer.'));
        };
    }
}

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
 * that exactly one of them is marked correct — the same for a question in
 * the question bank and for an MCQ on a board question paper. The two only
 * differ in how the option text is typed and where its image is stored.
 */
class McqOptionsSchema
{
    /**
     * @param  Component  $optionField  The `option` text field itself (a rich editor for question-bank questions, a plain input on board papers).
     * @return array<Component>
     */
    public static function components(Component $optionField, string $imageDirectory): array
    {
        return [
            $optionField,

            Checkbox::make('is_correct')
                ->label(__('Correct answer'))
                ->live()
                // Ticking one option unticks every other one of the question.
                ->afterStateUpdated(function (bool $state, Checkbox $component, Get $get, Set $set) {
                    if (! $state) {
                        return;
                    }

                    $currentItemKey = (string) Str::of($component->getStatePath())
                        ->beforeLast('.is_correct')
                        ->afterLast('.');

                    collect($get('../'))
                        ->keys()
                        ->reject(fn ($itemKey) => (string) $itemKey === $currentItemKey)
                        ->each(fn ($itemKey) => $set("../{$itemKey}.is_correct", false));
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

    public static function exactlyOneCorrectOptionRule(): Closure
    {
        // Filament evaluates whatever ->rule() is given through its own
        // closure-injection first (resolving parameters by name), which
        // can't resolve Laravel's $attribute/$value/$fail — so this outer,
        // zero-argument closure is what Filament calls, and its return
        // value (the real validator closure) is what Laravel then runs.
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
            if (collect($value)->contains(fn ($option) => (bool) ($option['is_correct'] ?? false))) {
                return;
            }

            $fail(__('Mark one option as the correct answer.'));
        };
    }
}

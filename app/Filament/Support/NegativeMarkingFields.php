<?php

namespace App\Filament\Support;

use App\Models\BillingSetting;
use App\Models\Exam;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The negative-marking switch and its amount on a teacher's exam — when
 * saving it from the question picker and when changing it later from the
 * exams list.
 */
class NegativeMarkingFields
{
    /**
     * @param  bool|Closure(): bool  $visible  Only an exam taken online is marked by the system.
     * @return array<int, Toggle|TextInput>
     */
    public static function make(bool|Closure $visible = true): array
    {
        $isVisible = fn (): bool => (bool) value($visible);

        return [
            Toggle::make('negative_marking')
                ->label(__('Negative marking'))
                ->helperText(__('Marks are taken off for each wrong answer online. A blank answer loses nothing, and the total never goes below 0.'))
                ->visible($isVisible)
                ->live(),
            TextInput::make('negative_marks')
                ->label(__('Marks taken off per wrong answer'))
                ->numeric()
                ->minValue(0.01)
                ->maxValue(100)
                ->step(0.01)
                ->visible(fn (Get $get): bool => $isVisible() && (bool) $get('negative_marking'))
                ->required(fn (Get $get): bool => (bool) $get('negative_marking')),
        ];
    }

    /**
     * The fields' starting state: the exam's own setting, the amount
     * starting on the Admin's default when it has none.
     *
     * @return array{negative_marking: bool, negative_marks: float|null}
     */
    public static function fill(?Exam $exam): array
    {
        return [
            'negative_marking' => ($exam?->marksLostPerWrongAnswer() ?? 0) > 0,
            'negative_marks' => $exam?->negative_marks ?: BillingSetting::current()->default_negative_marks,
        ];
    }

    /**
     * The submitted amount, or null when negative marking was left off.
     *
     * @param  array<string, mixed>  $data
     */
    public static function valueFrom(array $data): ?float
    {
        return ($data['negative_marking'] ?? false) ? (float) ($data['negative_marks'] ?? 0) : null;
    }
}

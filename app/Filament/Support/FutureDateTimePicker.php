<?php

namespace App\Filament\Support;

use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Carbon;

/**
 * A date-and-time field for something that is still to happen (when an exam
 * closes, when its answers unlock): minute precision, nothing in the past,
 * and the time zone spelled out so the teacher knows which clock it follows.
 */
class FutureDateTimePicker
{
    public static function make(string $name): DateTimePicker
    {
        return DateTimePicker::make($name)
            ->seconds(false)
            // Whole minutes only: the browser steps a minute-precision
            // field in 60s from its minimum, so a minimum of e.g. 11:34:41
            // would make it accept nothing but times ending in :41.
            ->minDate(fn () => now()->startOfMinute())
            ->helperText(__('Time zone: :timezone', ['timezone' => config('app.timezone')]));
    }

    /**
     * The submitted value as a date, or null when the field was left empty.
     */
    public static function parse(mixed $state): ?Carbon
    {
        return filled($state) ? Carbon::parse($state) : null;
    }
}

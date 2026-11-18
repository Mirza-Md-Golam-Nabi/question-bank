<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Which number filled in on a scanned OMR sheet tells the students apart
 * (`exams.omr_identity`; CLAUDE.md rule 10.11): the roll number (the
 * default) or the registration number. The scanner reads that grid of the
 * sheet, a number is saved once per exam by it, and a School's results are
 * matched by it.
 */
enum OmrIdentity: string implements HasLabel
{
    case Roll = 'roll';
    case Registration = 'registration';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Roll => __('Roll number'),
            self::Registration => __('Registration number'),
        };
    }

    /**
     * The sheet's grid it is read from (App\Support\OmrSheet::grids()).
     */
    public function grid(): string
    {
        return $this->value;
    }

    /**
     * What to say when a sheet is saved without it.
     */
    public function missingMessage(): string
    {
        return match ($this) {
            self::Roll => __('Write the roll number.'),
            self::Registration => __('Write the registration number.'),
        };
    }

    /**
     * What to say when the scanner could not read it clearly.
     */
    public function unclearMessage(): string
    {
        return match ($this) {
            self::Roll => __('The roll could not be read clearly — check it.'),
            self::Registration => __('The registration number could not be read clearly — check it.'),
        };
    }

    /**
     * "Roll 7"/ "Registration 1234" — a scanned sheet on the result sheet.
     */
    public function describe(string $number): string
    {
        return match ($this) {
            self::Roll => __('Roll :roll', ['roll' => $number]),
            self::Registration => __('Registration :number', ['number' => $number]),
        };
    }
}

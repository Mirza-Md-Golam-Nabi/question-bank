<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * How a Teacher or School likes their printed question papers laid out —
 * columns, paper size, margins, fonts, which header lines show, the
 * instructions… Kept on the user (`users.print_settings`), so the same
 * look follows them to every device and every exam. The one place that
 * knows the settings, their starting values and their limits; the print
 * page (resources/js/print-settings.js) only applies them.
 *
 * `mcq_instructions` / `cq_instructions` null = the Admin's text
 * (BillingSetting.print_*_instructions). `institution_name_id` is only a
 * wish — the page prints it only while that name is usable.
 */
final class PrintSettings
{
    public const PAPER_SIZES = ['A4', 'Letter', 'Legal', 'A5'];

    public const OPTION_STYLES = ['paren', 'circle', 'dot', 'bracket'];

    public const TEXT_ALIGNS = ['left', 'center', 'right', 'justify'];

    public const PAGE_NUMBER_POSITIONS = ['center', 'right'];

    /**
     * The fonts a paper can be printed in, by key — each loaded by the
     * Vite fonts plugin (vite.config.js).
     *
     * @var array<string, array{label: string, family: string}>
     */
    public const FONTS = [
        'hind' => ['label' => 'Hind Siliguri', 'family' => "'Hind Siliguri', sans-serif"],
        'noto_sans' => ['label' => 'Noto Sans Bengali', 'family' => "'Noto Sans Bengali', sans-serif"],
        'noto_serif' => ['label' => 'Noto Serif Bengali', 'family' => "'Noto Serif Bengali', serif"],
        'tiro' => ['label' => 'Tiro Bangla', 'family' => "'Tiro Bangla', serif"],
    ];

    /**
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'institution_name_id' => null,
        'institution_font_size' => 26,
        'show_exam_title' => true,
        'show_class' => true,
        'show_subject' => true,
        'show_chapters' => false,
        'show_instructions' => true,
        'mcq_instructions' => null,
        'cq_instructions' => null,
        'paper_size' => 'A4',
        'margin_top' => 12,
        'margin_right' => 10,
        'margin_bottom' => 12,
        'margin_left' => 10,
        'page_numbers' => false,
        'page_number_position' => 'center',
        'text_align' => 'left',
        'option_style' => 'paren',
        'options_per_row' => 2,
        'font' => 'hind',
        'font_size' => 14,
        'columns' => 2,
        'column_divider' => true,
        'question_gap' => 8,
        'column_gap' => 20,
    ];

    /**
     * The user's settings, every one present — a saved value that no
     * longer passes its rule (an older version's) falls back to its default.
     *
     * @return array<string, mixed>
     */
    public static function for(User $user): array
    {
        $saved = array_intersect_key($user->print_settings ?? [], self::DEFAULTS);
        $settings = self::DEFAULTS;

        foreach ($saved as $key => $value) {
            if (! Validator::make([$key => $value], [$key => self::rules()[$key]])->fails()) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * Saves what the print page sent: only the known settings, each within
     * its limits.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public static function save(User $user, array $input): void
    {
        $validated = Validator::make($input, self::rules())->validate();

        $user->forceFill(['print_settings' => [...self::for($user), ...$validated]])->save();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'institution_name_id' => ['nullable', 'integer'],
            'institution_font_size' => ['integer', 'between:14,48'],
            'show_exam_title' => ['boolean'],
            'show_class' => ['boolean'],
            'show_subject' => ['boolean'],
            'show_chapters' => ['boolean'],
            'show_instructions' => ['boolean'],
            'mcq_instructions' => ['nullable', 'string', 'max:1000'],
            'cq_instructions' => ['nullable', 'string', 'max:1000'],
            'paper_size' => [Rule::in(self::PAPER_SIZES)],
            'margin_top' => ['integer', 'between:3,40'],
            'margin_right' => ['integer', 'between:3,40'],
            'margin_bottom' => ['integer', 'between:3,40'],
            'margin_left' => ['integer', 'between:3,40'],
            'page_numbers' => ['boolean'],
            'page_number_position' => [Rule::in(self::PAGE_NUMBER_POSITIONS)],
            'text_align' => [Rule::in(self::TEXT_ALIGNS)],
            'option_style' => [Rule::in(self::OPTION_STYLES)],
            'options_per_row' => [Rule::in([1, 2, 4])],
            'font' => [Rule::in(array_keys(self::FONTS))],
            'font_size' => ['integer', 'between:9,24'],
            'columns' => [Rule::in([1, 2, 3])],
            'column_divider' => ['boolean'],
            'question_gap' => ['integer', 'between:0,40'],
            'column_gap' => ['integer', 'between:0,60'],
        ];
    }
}

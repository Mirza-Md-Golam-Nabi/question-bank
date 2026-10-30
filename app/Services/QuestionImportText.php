<?php

namespace App\Services;

/**
 * One piece of text of an imported question — a question, an option, a CQ
 * sub-question — as it is written in the JSON: plain text with any maths
 * between dollar signs (`$x^2$`), and, except in an option, tables written
 * the Markdown way (`| a | b |` rows, a `|---|---|` line under the header).
 * Decides what is wrong with such a text and turns it into the HTML
 * questions are stored as, where a formula is
 * `<span class="qb-katex-embed">latex</span>` and a table is CKEditor's
 * `<figure class="table"><table>…</table></figure>`.
 *
 * Whether KaTeX can actually draw a formula is only known in the browser;
 * what can be told here is whether it is put together properly.
 */
class QuestionImportText
{
    /**
     * A technical guard, not a business rule: no real formula comes near it.
     */
    public const MAX_FORMULA_LENGTH = 500;

    /**
     * A formula between single or double dollar signs; `\$` is a literal
     * dollar sign, not a delimiter.
     */
    private const FORMULA_PATTERN = '/(?<!\\\\)(\${1,2})(.+?)(?<!\\\\)\1/su';

    /**
     * The line under a table's header row: `|---|:---:|`, its outer pipes
     * optional.
     */
    private const TABLE_DIVIDER_PATTERN = '/^\s*\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)*\|?\s*$/';

    /**
     * What is wrong with the text, in the user's language — empty when
     * nothing is.
     *
     * @return array<int, string>
     */
    public static function problemsIn(string $text, bool $allowsTables = true): array
    {
        $problems = [];

        foreach (self::blocks($text) as $block) {
            if (! isset($block['rows'])) {
                array_push($problems, ...self::inlineProblems($block['text']));

                continue;
            }

            if (! $allowsTables) {
                $problems[] = __('an option cannot have a table');

                continue;
            }

            array_push($problems, ...self::tableProblems($block['rows']));

            foreach ($block['rows'] as $cells) {
                foreach ($cells as $cell) {
                    array_push($problems, ...self::inlineProblems($cell));
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Everything is escaped, so nothing in the JSON can arrive as markup —
     * a table is built here from its cells, never taken as written.
     */
    public static function toHtml(string $text, bool $allowsTables = true): string
    {
        if (! $allowsTables) {
            return '<p>'.self::inlineHtml(trim($text)).'</p>';
        }

        return collect(self::blocks($text))
            ->map(fn (array $block): string => isset($block['rows'])
                ? self::tableHtml($block['rows'])
                : '<p>'.self::inlineHtml(trim($block['text'])).'</p>')
            ->implode('');
    }

    /**
     * @return array<int, string>
     */
    private static function inlineProblems(string $text): array
    {
        $problems = [];

        foreach (self::segments($text) as $segment) {
            array_push($problems, ...($segment['is_formula']
                ? self::formulaProblems($segment['text'])
                : self::plainTextProblems($segment['text'])));
        }

        return $problems;
    }

    /**
     * A stretch of text with its formulas, as HTML with no paragraph
     * around it. `\$` and `\|` are a plain dollar sign and bar.
     */
    private static function inlineHtml(string $text): string
    {
        $html = '';

        foreach (self::segments($text) as $segment) {
            $html .= $segment['is_formula']
                ? '<span class="qb-katex-embed">'.e(trim($segment['text'])).'</span>'
                : nl2br(e(str_replace(['\$', '\|'], ['$', '|'], $segment['text'])), false);
        }

        return $html;
    }

    /**
     * The text cut into its paragraphs and its tables, in order. A table is
     * a header row, the divider line under it, and every `|` row after
     * that; anything else — a lone `|x|` included — is text.
     *
     * @return array<int, array{text: string}|array{rows: array<int, array<int, string>>}>
     */
    private static function blocks(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [''];
        $blocks = [];
        $paragraph = [];

        for ($index = 0; $index < count($lines); $index++) {
            if (! self::isTableRow($lines[$index]) || ! preg_match(self::TABLE_DIVIDER_PATTERN, $lines[$index + 1] ?? '')) {
                $paragraph[] = $lines[$index];

                continue;
            }

            if (trim(implode("\n", $paragraph)) !== '') {
                $blocks[] = ['text' => implode("\n", $paragraph)];
            }

            $paragraph = [];
            $rows = [self::cellsOf($lines[$index])];
            $index += 2;

            while ($index < count($lines) && self::isTableRow($lines[$index])) {
                $rows[] = self::cellsOf($lines[$index]);
                $index++;
            }

            $index--;
            $blocks[] = ['rows' => $rows];
        }

        if ($blocks === [] || trim(implode("\n", $paragraph)) !== '') {
            $blocks[] = ['text' => implode("\n", $paragraph)];
        }

        return $blocks;
    }

    private static function isTableRow(string $line): bool
    {
        return str_starts_with(trim($line), '|');
    }

    /**
     * A row's cells: split at each `|` that is not written `\|` and not
     * inside a formula (where it may be an absolute value, `$|x|$`).
     *
     * @return array<int, string>
     */
    private static function cellsOf(string $line): array
    {
        // The bars at the two ends of a row only close it off.
        $line = preg_replace('/^\|/', '', trim($line)) ?? $line;
        $line = preg_replace('/(?<!\\\\)\|$/', '', $line) ?? $line;
        $cells = [];
        $cell = '';
        $inFormula = false;
        $previous = '';

        foreach (mb_str_split($line) as $character) {
            $isEscaped = $previous === '\\';
            $previous = $character;

            if ($character === '$' && ! $isEscaped) {
                $inFormula = ! $inFormula;
            }

            if ($character === '|' && ! $isEscaped && ! $inFormula) {
                $cells[] = trim($cell);
                $cell = '';

                continue;
            }

            $cell .= $character;
        }

        $cells[] = trim($cell);

        return $cells;
    }

    /**
     * @param  array<int, array<int, string>>  $rows  The header row first.
     * @return array<int, string>
     */
    private static function tableProblems(array $rows): array
    {
        $columns = count($rows[0]);

        if (count($rows) < 2) {
            return [__('a table has no rows under its header')];
        }

        foreach (array_slice($rows, 1) as $cells) {
            if (count($cells) !== $columns) {
                return [__('a table row has :count cells, but its header has :columns', ['count' => count($cells), 'columns' => $columns])];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<int, string>>  $rows  The header row first.
     */
    private static function tableHtml(array $rows): string
    {
        $row = fn (array $cells, string $tag): string => '<tr>'.collect($cells)
            ->map(fn (string $cell): string => "<{$tag}>".self::inlineHtml($cell)."</{$tag}>")
            ->implode('').'</tr>';

        return '<figure class="table"><table>'
            .'<thead>'.$row(array_shift($rows), 'th').'</thead>'
            .'<tbody>'.collect($rows)->map(fn (array $cells): string => $row($cells, 'td'))->implode('').'</tbody>'
            .'</table></figure>';
    }

    /**
     * The text cut into its plain stretches and its formulas, in order.
     *
     * @return array<int, array{is_formula: bool, text: string}>
     */
    private static function segments(string $text): array
    {
        // With the two capture groups kept, the pieces come in threes:
        // plain text, the delimiter, the formula between it.
        $pieces = preg_split(self::FORMULA_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($pieces === false) {
            return [['is_formula' => false, 'text' => $text]];
        }

        $segments = [];

        foreach ($pieces as $index => $piece) {
            if ($index % 3 === 1 || ($index % 3 === 0 && $piece === '')) {
                continue;
            }

            $segments[] = ['is_formula' => $index % 3 === 2, 'text' => $piece];
        }

        return $segments;
    }

    /**
     * @return array<int, string>
     */
    private static function plainTextProblems(string $text): array
    {
        $problems = [];

        if (preg_match('/(?<!\\\\)\$/', $text)) {
            $problems[] = __('a $ sign has no partner — a formula goes between two of them, and a plain dollar sign is written \$');
        }

        // A line break is fine in plain text; any other control character
        // is what JSON makes of a single backslash (`\t`, `\f`, `\b`…).
        if (preg_match('/[\x00-\x09\x0B-\x1F\x7F]/', $text)) {
            $problems[] = self::singleBackslashProblem();
        }

        return $problems;
    }

    /**
     * @return array<int, string>
     */
    private static function formulaProblems(string $latex): array
    {
        if (trim($latex) === '') {
            return [__('there is an empty formula')];
        }

        $problems = [];

        // `\frac` written with one backslash reaches us as a form feed
        // followed by "rac", `\times` as a tab followed by "imes", and so
        // on — so a control character inside a formula is always that.
        if (preg_match('/[\x00-\x1F\x7F]/', $latex)) {
            $problems[] = self::singleBackslashProblem();
        }

        if (preg_match('/[\x{0980}-\x{09FF}]/u', self::withoutTextArguments($latex))) {
            $problems[] = __('a formula contains Bangla letters outside \text{…} — write Bangla inside a formula as :example', ['example' => '\\\\text{মোট সরণ}']);
        }

        if (mb_strlen($latex) > self::MAX_FORMULA_LENGTH) {
            $problems[] = __('a formula is longer than :max characters', ['max' => self::MAX_FORMULA_LENGTH]);
        }

        if (! self::hasBalancedBraces($latex)) {
            $problems[] = __('the { } brackets of a formula do not match');
        }

        if (preg_match_all('/\\\\left(?![a-zA-Z])/', $latex) !== preg_match_all('/\\\\right(?![a-zA-Z])/', $latex)) {
            $problems[] = __('a formula has a \left without its \right (or the other way round)');
        }

        if (! self::hasMatchingEnvironments($latex)) {
            $problems[] = __('a formula has a \begin{…} without its matching \end{…}');
        }

        return $problems;
    }

    private static function singleBackslashProblem(): string
    {
        return __('a backslash was written once — in JSON every backslash of a formula is written twice (:twice, not \frac)', ['twice' => '\\\\frac']);
    }

    /**
     * The formula with what is inside its text commands (`\text{…}`,
     * `\mbox{…}`…) taken out: KaTeX draws that as ordinary text, so Bangla
     * is fine there — in the maths itself it comes out broken.
     */
    private static function withoutTextArguments(string $latex): string
    {
        $result = '';
        $length = strlen($latex);
        $index = 0;

        while ($index < $length) {
            if (! preg_match('/\G\\\\(?:text(?:rm|bf|it|sf|tt|normal|up)?|mbox)\s*\{/', $latex, $match, 0, $index)) {
                $result .= $latex[$index++];

                continue;
            }

            // Past the argument's closing brace, nested braces included.
            $index += strlen($match[0]);

            for ($depth = 1; $index < $length && $depth > 0; $index++) {
                if ($latex[$index] === '\\') {
                    $index++;
                } elseif ($latex[$index] === '{') {
                    $depth++;
                } elseif ($latex[$index] === '}') {
                    $depth--;
                }
            }
        }

        return $result;
    }

    private static function hasBalancedBraces(string $latex): bool
    {
        // `\\` (a line break) and the escaped `\{` `\}` are not brackets.
        $brackets = preg_replace('/\\\\\\\\|\\\\[{}]|[^{}]/u', '', $latex) ?? '';
        $depth = 0;

        foreach (str_split($brackets) as $bracket) {
            $depth += $bracket === '{' ? 1 : -1;

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }

    private static function hasMatchingEnvironments(string $latex): bool
    {
        preg_match_all('/\\\\(begin|end)\s*\{([^}]*)\}/', $latex, $matches, PREG_SET_ORDER);

        $open = [];

        foreach ($matches as [, $command, $environment]) {
            if ($command === 'begin') {
                $open[] = $environment;
            } elseif (array_pop($open) !== $environment) {
                return false;
            }
        }

        return $open === [];
    }
}

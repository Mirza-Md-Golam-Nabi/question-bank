<?php

namespace App\Services;

/**
 * One piece of text of an imported question — a question, an option, a CQ
 * sub-question — as it is written in the JSON: plain text with any maths
 * between dollar signs (`$x^2$`). Decides what is wrong with such a text
 * and turns it into the HTML questions are stored as, where a formula is
 * `<span class="qb-katex-embed">latex</span>`.
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
     * What is wrong with the text, in the user's language — empty when
     * nothing is.
     *
     * @return array<int, string>
     */
    public static function problemsIn(string $text): array
    {
        $problems = [];

        foreach (self::segments($text) as $segment) {
            array_push($problems, ...($segment['is_formula']
                ? self::formulaProblems($segment['text'])
                : self::plainTextProblems($segment['text'])));
        }

        return array_values(array_unique($problems));
    }

    /**
     * Everything is escaped, so nothing in the JSON can arrive as markup.
     */
    public static function toHtml(string $text): string
    {
        $html = '';

        foreach (self::segments(trim($text)) as $segment) {
            $html .= $segment['is_formula']
                ? '<span class="qb-katex-embed">'.e(trim($segment['text'])).'</span>'
                : nl2br(e(str_replace('\$', '$', $segment['text'])), false);
        }

        return "<p>{$html}</p>";
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

        if (preg_match('/[\x{0980}-\x{09FF}]/u', $latex)) {
            $problems[] = __('a formula contains Bangla letters — keep Bangla text outside the $ signs');
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

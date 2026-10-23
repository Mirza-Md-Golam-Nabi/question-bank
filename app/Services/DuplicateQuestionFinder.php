<?php

namespace App\Services;

use App\Filament\Support\QuestionBlocks;
use App\Models\BillingSetting;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Tells whether a question is already there in another form — the one
 * place duplicates are judged. The JSON imports (a chapter's, a past
 * paper's), the question forms and the Admin's approval all ask here; what
 * they compare against (a chapter, a paper) is theirs to say.
 *
 * Both questions are first normalized: tags, punctuation and extra spaces
 * gone, Bangla digits as ASCII, a formula kept whole. A shared stimulus
 * counts as part of its question. Then, for questions of the same type:
 *
 *  1. exact — the same question and the same options (in any order) or
 *     CQ sub-questions: a sure duplicate;
 *  2. never — however alike they look — when the questions' numbers,
 *     formulas or negating words differ: "কোনটি মৌলিক রাশি নয়" is not
 *     "কোনটি মৌলিক রাশি", nor "1 Pa" "1 kPa";
 *  3. similar — the question, and its options or sub-questions, are each
 *     at least the Admin's percentage alike (letter trigrams, Dice): a
 *     possible duplicate, for a person to decide.
 */
final class DuplicateQuestionFinder
{
    /**
     * Words that turn a question around; two questions differing in them
     * are never duplicates.
     */
    private const TURNING_WORDS = [
        'না', 'নয়', 'নেই', 'নাই', 'নহে', 'ব্যতীত', 'ছাড়া', 'ব্যতিক্রম',
        'সঠিক', 'ভুল', 'সত্য', 'মিথ্যা', 'অসত্য',
        'not', 'except', 'correct', 'incorrect', 'true', 'false', 'wrong',
    ];

    /**
     * A question's normalized form, worked out once per question.
     *
     * @var array<int, array{type: string, question: string, answers: string, guard: string, questionGrams: array<string, true>, answerGrams: array<string, true>}>
     */
    private array $profiles = [];

    /**
     * @var array<string, true>|null
     */
    private ?array $turningWords = null;

    public function __construct(private readonly int $similarPercent) {}

    /**
     * At the Admin's percentage.
     */
    public static function make(): self
    {
        return new self(BillingSetting::current()->duplicate_similarity_percent);
    }

    /**
     * What `$question` duplicates among the candidates, if anything — the
     * same question before the most alike one.
     *
     * @param  iterable<Model>  ...$candidateLists
     */
    public function bestMatch(Model $question, iterable ...$candidateLists): ?DuplicateMatch
    {
        $best = null;

        foreach ($candidateLists as $candidates) {
            foreach ($candidates as $candidate) {
                $match = $candidate === $question ? null : $this->compare($question, $candidate);

                if ($match?->isExact) {
                    return $match;
                }

                if ($match && $match->percent > ($best?->percent ?? 0)) {
                    $best = $match;
                }
            }
        }

        return $best;
    }

    public function compare(Model $question, Model $other): ?DuplicateMatch
    {
        $one = $this->profile($question);
        $two = $this->profile($other);

        if ($one['type'] !== $two['type']) {
            return null;
        }

        if ($one['question'] === $two['question'] && $one['answers'] === $two['answers']) {
            return new DuplicateMatch($other, 100, true);
        }

        if ($one['guard'] !== $two['guard']) {
            return null;
        }

        $questionPercent = $this->likeness($one['questionGrams'], $two['questionGrams']);

        if ($questionPercent < $this->similarPercent) {
            return null;
        }

        $answerPercent = $this->likeness($one['answerGrams'], $two['answerGrams']);

        if ($answerPercent < $this->similarPercent) {
            return null;
        }

        return new DuplicateMatch($other, (int) floor(min($questionPercent, $answerPercent)), false);
    }

    /**
     * A question's text (or an option, or a sub-question) as it is
     * compared: formulas whole with their spaces taken out, everything else
     * without tags, punctuation, case and extra spaces, Bangla digits as
     * ASCII.
     *
     * @return array{text: string, numbers: list<string>, formulas: list<string>}
     */
    public static function normalize(?string $html): array
    {
        $formulas = [];

        // Each formula steps aside as a placeholder, so the punctuation
        // that is part of it ("10^{-3}") survives.
        $text = preg_replace_callback(
            '/<span[^>]*class="[^"]*qb-katex-embed[^"]*"[^>]*>(.*?)<\/span>/su',
            function (array $found) use (&$formulas): string {
                $formulas[] = preg_replace('/\s+/u', '', html_entity_decode(strip_tags($found[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                return ' '."\x01".(count($formulas) - 1)."\x01".' ';
            },
            (string) $html,
        );

        $text = self::plainBangla(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = mb_strtolower($text);
        // A decimal point stays; every other punctuation mark goes.
        $text = preg_replace('/(\d)\.(\d)/u', "$1\x02$2", $text);
        $text = preg_replace('/\p{P}+/u', ' ', $text);
        $text = str_replace("\x02", '.', $text);

        preg_match_all('/\d+(?:\.\d+)?/', preg_replace('/\x01\d+\x01/', ' ', $text), $numbers);

        $text = preg_replace_callback('/\x01(\d+)\x01/', fn (array $found): string => $formulas[(int) $found[1]], $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return ['text' => $text, 'numbers' => $numbers[0], 'formulas' => $formulas];
    }

    /**
     * @return array{type: string, question: string, answers: string, guard: string, questionGrams: array<string, true>, answerGrams: array<string, true>}
     */
    private function profile(Model $question): array
    {
        return $this->profiles[spl_object_id($question)] ??= $this->makeProfile($question);
    }

    /**
     * @return array{type: string, question: string, answers: string, guard: string, questionGrams: array<string, true>, answerGrams: array<string, true>}
     */
    private function makeProfile(Model $question): array
    {
        $stimulus = QuestionBlocks::stimulusOf($question);
        $asked = self::normalize(($stimulus ? $stimulus->text.' ' : '').$question->question_text);

        // An MCQ is answered by its options, a CQ by its sub-questions.
        $isMcq = $question->options !== null;
        // An option that is (or has) a picture is told apart by it too:
        // two picture-only options are never "the same" for being blank.
        $answers = collect($isMcq ? $question->options : $question->cqParts)
            ->map(fn (mixed $answer): string => trim(self::normalize(data_get($answer, $isMcq ? 'option' : 'part_text'))['text']
                .($isMcq && ($picture = self::optionPicture($answer)) ? ' #'.$picture : '')));

        // Options are the same in any order; sub-questions keep theirs.
        $answers = ($isMcq ? $answers->sort() : $answers)->implode(' | ');

        $words = array_flip(explode(' ', $asked['text']));
        $turning = array_keys(array_intersect_key($this->turningWords(), $words));
        sort($turning);

        $numbers = $asked['numbers'];
        $formulas = $asked['formulas'];
        sort($numbers);
        sort($formulas);

        $type = $question->question_type;

        return [
            'type' => $type instanceof BackedEnum ? (string) $type->value : (string) $type,
            'question' => $asked['text'],
            'answers' => $answers,
            'guard' => implode("\x00", [implode(',', $numbers), implode(',', $formulas), implode(',', $turning)]),
            'questionGrams' => self::grams($asked['text']),
            'answerGrams' => self::grams($answers),
        ];
    }

    /**
     * An option's picture, by its file name — stored as one path, but still
     * a list of uploads (path or file) while the form is being filled in.
     */
    private static function optionPicture(mixed $option): ?string
    {
        $picture = data_get($option, 'image');
        $picture = is_array($picture) ? reset($picture) : $picture;

        if ($picture instanceof UploadedFile) {
            return $picture->getClientOriginalName();
        }

        return filled($picture) && is_string($picture) ? basename($picture) : null;
    }

    /**
     * How alike two texts are by their three-letter pieces (Dice), in percent.
     *
     * @param  array<string, true>  $one
     * @param  array<string, true>  $two
     */
    private function likeness(array $one, array $two): float
    {
        $total = count($one) + count($two);

        if ($total === 0) {
            return 100.0;
        }

        // Even complete overlap of the smaller can't reach the bar.
        if (200 * min(count($one), count($two)) / $total < $this->similarPercent) {
            return 0.0;
        }

        return 200 * count(array_intersect_key($one, $two)) / $total;
    }

    /**
     * @return array<string, true>
     */
    private static function grams(string $text): array
    {
        $letters = mb_str_split($text);

        if (count($letters) < 3) {
            return $text === '' ? [] : [$text => true];
        }

        $grams = [];

        for ($at = 0, $last = count($letters) - 3; $at <= $last; $at++) {
            $grams[$letters[$at].$letters[$at + 1].$letters[$at + 2]] = true;
        }

        return $grams;
    }

    /**
     * Bangla digits as ASCII, and য়/ড়/ঢ় in their one-character form
     * whichever way they were typed.
     */
    private static function plainBangla(string $text): string
    {
        return strtr($text, [
            "\u{09E6}" => '0', "\u{09E7}" => '1', "\u{09E8}" => '2', "\u{09E9}" => '3', "\u{09EA}" => '4',
            "\u{09EB}" => '5', "\u{09EC}" => '6', "\u{09ED}" => '7', "\u{09EE}" => '8', "\u{09EF}" => '9',
            "\u{09AF}\u{09BC}" => "\u{09DF}", "\u{09A1}\u{09BC}" => "\u{09DC}", "\u{09A2}\u{09BC}" => "\u{09DD}",
        ]);
    }

    /**
     * @return array<string, true>
     */
    private function turningWords(): array
    {
        return $this->turningWords ??= array_fill_keys(
            array_map(fn (string $word): string => self::normalize($word)['text'], self::TURNING_WORDS),
            true,
        );
    }
}

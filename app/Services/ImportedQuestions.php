<?php

namespace App\Services;

use App\Filament\Support\QuestionBlocks;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What both JSON importers (a chapter's, a past paper's) do with what
 * QuestionJsonReader read, whatever their models: turn its entries into
 * questions — the MCQs of a stimulus each pointing at that one stimulus —
 * find which of them look like questions already there, and save them,
 * leaving out the same ones and the possible duplicates not accepted.
 */
final class ImportedQuestions
{
    /**
     * The field of an import form the problems with its images are
     * reported against.
     */
    public const IMAGES_KEY = 'images';

    /**
     * The entries as unsaved questions in their order; the MCQs of a
     * stimulus share one unsaved stimulus and carry their place under it.
     * Every image is where ImportedImages keeps it for now.
     *
     * @param  array<int, array<string, mixed>>  $entries  What QuestionJsonReader::read() returned.
     * @param  Closure(array<string, mixed>): Model  $question  Builds one question from its read entry (its images already paths).
     * @param  Closure(string, ?string): Model  $stimulus  Builds a stimulus from its read text and its image's path.
     * @return Collection<int, Model>
     *
     * @throws ValidationException naming every image a question asks for that is not there to take
     */
    public static function build(array $entries, Closure $question, Closure $stimulus, ImportedImages $images): Collection
    {
        self::checkImages($entries, $images);

        return collect($entries)->flatMap(function (array $entry) use ($question, $stimulus, $images): array {
            if (! isset($entry['stimulus'])) {
                return [$question(self::withImagePaths($entry, $images))];
            }

            $shared = $stimulus($entry['stimulus'], $images->pathOf($entry['stimulus_image'] ?? null));

            return collect($entry['questions'])->values()->map(
                fn (array $item, int $index): Model => $question(self::withImagePaths($item, $images))
                    ->fill(['stimulus_order' => $index + 1])
                    ->setRelation('stimulus', $shared),
            )->all();
        })->values();
    }

    /**
     * Refuses the import, naming each, when an image a question asks for
     * was not given or could not be taken — a question about "the figure"
     * is no question without it — or when the images as a whole were too
     * much. Questions are counted as in the JSON.
     *
     * @param  array<int, array<string, mixed>>  $entries
     *
     * @throws ValidationException
     */
    private static function checkImages(array $entries, ImportedImages $images): void
    {
        $asked = [];

        foreach (array_values($entries) as $index => $entry) {
            $number = $index + 1;

            if (isset($entry['stimulus'])) {
                $asked[] = [$entry['stimulus_image'] ?? null, __('the stimulus of question :number', ['number' => $number])];

                foreach (array_values($entry['questions']) as $inner => $item) {
                    array_push($asked, ...self::imagesAskedBy($item, __('question :inner under the stimulus of question :number', ['inner' => $inner + 1, 'number' => $number])));
                }

                continue;
            }

            array_push($asked, ...self::imagesAskedBy($entry, __('question :number', ['number' => $number])));
        }

        $problems = collect($asked)
            ->filter(fn (array $image): bool => $image[0] !== null)
            ->map(fn (array $image): ?string => ($problem = $images->problemWith($image[0]))
                ? __(':file (:place): :problem', ['file' => $image[0], 'place' => $image[1], 'problem' => $problem])
                : null)
            ->filter()
            ->merge($images->problems())
            ->values()
            ->all();

        if ($problems !== []) {
            throw ValidationException::withMessages([self::IMAGES_KEY => $problems]);
        }
    }

    /**
     * The images one question asks for: its own, its options' and its
     * sub-questions', each with where it goes.
     *
     * @param  array<string, mixed>  $item
     * @return list<array{0: ?string, 1: string}>
     */
    private static function imagesAskedBy(array $item, string $place): array
    {
        return [
            [$item['image'] ?? null, $place],
            ...collect($item['options'] ?? [])->values()->map(fn (array $option, int $index): array => [
                $option['image'] ?? null,
                __('option :option of :place', ['option' => $index + 1, 'place' => $place]),
            ])->all(),
            ...collect($item['parts'] ?? [])->values()->map(fn (array $part, int $index): array => [
                $part['part_image'] ?? null,
                __('sub-question :part of :place', ['part' => $index + 1, 'place' => $place]),
            ])->all(),
        ];
    }

    /**
     * A read question with its images' file names turned into the paths
     * they are kept at for now.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function withImagePaths(array $item, ImportedImages $images): array
    {
        return [
            ...$item,
            'image' => $images->pathOf($item['image'] ?? null),
            ...(isset($item['options']) ? ['options' => array_map(fn (array $option): array => [...$option, 'image' => $images->pathOf($option['image'] ?? null)], $item['options'])] : []),
            ...(isset($item['parts']) ? ['parts' => array_map(fn (array $part): array => [...$part, 'part_image' => $images->pathOf($part['part_image'] ?? null)], $item['parts'])] : []),
        ];
    }

    /**
     * Which of the questions duplicate one already there, or one earlier in
     * the same JSON (DuplicateQuestionFinder) — keyed by the question's
     * place in `$questions`. The MCQs of one stimulus are not held against
     * each other.
     *
     * @param  Collection<int, Model>  $questions  What build() returned.
     * @param  Collection<int, Model>  $alreadyThere  What they are checked against.
     * @return array<int, DuplicateMatch>
     */
    public static function duplicates(Collection $questions, Collection $alreadyThere, DuplicateQuestionFinder $finder): array
    {
        $questions = $questions->values();
        $matches = [];
        $earlier = [];

        foreach (self::units($questions) as $unit) {
            foreach ($unit as $index) {
                if ($match = $finder->bestMatch($questions[$index], $alreadyThere, $earlier)) {
                    $matches[$index] = $match;
                }
            }

            foreach ($unit as $index) {
                if (! ($matches[$index] ?? null)?->isExact) {
                    $earlier[] = $questions[$index];
                }
            }
        }

        return $matches;
    }

    /**
     * The places of the questions in their units — a question alone, or
     * the MCQs of one stimulus — keyed by the place of each unit's first.
     * A unit is added or left out as a whole.
     *
     * @param  Collection<int, Model>  $questions
     * @return array<int, list<int>>
     */
    public static function units(Collection $questions): array
    {
        $questions = $questions->values();
        $units = [];

        foreach (QuestionBlocks::units($questions) as $unit) {
            $places = array_map(fn (Model $question): int => $questions->search($question, strict: true), $unit);
            $units[$places[0]] = $places;
        }

        return $units;
    }

    /**
     * Whether a unit is left out without asking: every question of it is
     * already there as it is.
     *
     * @param  list<int>  $unit
     * @param  array<int, DuplicateMatch>  $matches
     */
    public static function isAlreadyThere(array $unit, array $matches): bool
    {
        return collect($unit)->every(fn (int $index): bool => (bool) ($matches[$index] ?? null)?->isExact);
    }

    /**
     * Whether a unit needs the user's word to be added: something in it
     * looks like a question already there, but it is not all the same.
     *
     * @param  list<int>  $unit
     * @param  array<int, DuplicateMatch>  $matches
     */
    public static function isPossibleDuplicate(array $unit, array $matches): bool
    {
        return ! self::isAlreadyThere($unit, $matches) && collect($unit)->contains(fn (int $index): bool => isset($matches[$index]));
    }

    /**
     * The matches without those of questions already there as they are —
     * for a writer whose questions go through the background duplicate
     * check (QuestionDuplicateScreening): such a question is added, the
     * Admin deciding on it, and only the possible duplicates are asked about.
     *
     * @param  array<int, DuplicateMatch>  $matches
     * @return array<int, DuplicateMatch>
     */
    public static function withoutExact(array $matches): array
    {
        return array_filter($matches, fn (DuplicateMatch $match): bool => ! $match->isExact);
    }

    /**
     * Saves the questions, each stimulus first. A unit already there is
     * left out; a possible duplicate is added only when the user accepted
     * it (by the place of its first question).
     *
     * @param  Collection<int, Model>  $questions  What build() returned.
     * @param  array<int, DuplicateMatch>  $matches  What duplicates() found.
     * @param  array<int, int>  $accepted  The possible duplicates to add anyway.
     * @param  Closure(Model): void  $save  Saves one question (and anything of its own, like a CQ's parts).
     * @param  (Closure(Model): void)|null  $prepare  Done to each stimulus and question just before it is saved (its images published).
     * @return array{added: int, skipped: int, possible_duplicates: int} How many questions were added, left out as already there, and left out as possible duplicates.
     */
    public static function save(Collection $questions, array $matches, array $accepted, Closure $save, ?Closure $prepare = null): array
    {
        $questions = $questions->values();
        $result = ['added' => 0, 'skipped' => 0, 'possible_duplicates' => 0];

        foreach (self::units($questions) as $first => $unit) {
            if (self::isAlreadyThere($unit, $matches)) {
                $result['skipped'] += count($unit);

                continue;
            }

            if (self::isPossibleDuplicate($unit, $matches) && ! in_array($first, $accepted, strict: false)) {
                $result['possible_duplicates'] += count($unit);

                continue;
            }

            $stimulus = QuestionBlocks::stimulusOf($questions[$first]);

            if ($stimulus) {
                $prepare && $prepare($stimulus);
                $stimulus->save();
            }

            foreach ($unit as $index) {
                $question = $questions[$index];

                if ($stimulus) {
                    $question->stimulus_id = $stimulus->id;
                    $question->unsetRelation('stimulus');
                }

                $prepare && $prepare($question);
                $save($question);
                $result['added']++;
            }
        }

        return $result;
    }
}

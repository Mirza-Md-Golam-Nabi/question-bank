<?php

namespace App\Services;

use App\Filament\Support\QuestionBlocks;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * What both JSON importers (a chapter's, a past paper's) do with what
 * QuestionJsonReader read, whatever their models: turn its entries into
 * questions — the MCQs of a stimulus each pointing at that one stimulus —
 * and save them, leaving out what is already there.
 */
final class ImportedQuestions
{
    /**
     * The entries as unsaved questions in their order; the MCQs of a
     * stimulus share one unsaved stimulus and carry their place under it.
     *
     * @param  array<int, array<string, mixed>>  $entries  What QuestionJsonReader::read() returned.
     * @param  Closure(array<string, mixed>): Model  $question  Builds one question from its read entry.
     * @param  Closure(string): Model  $stimulus  Builds a stimulus from its read text.
     * @return Collection<int, Model>
     */
    public static function build(array $entries, Closure $question, Closure $stimulus): Collection
    {
        return collect($entries)->flatMap(function (array $entry) use ($question, $stimulus): array {
            if (! isset($entry['stimulus'])) {
                return [$question($entry)];
            }

            $shared = $stimulus($entry['stimulus']);

            return collect($entry['questions'])->values()->map(
                fn (array $item, int $index): Model => $question($item)
                    ->fill(['stimulus_order' => $index + 1])
                    ->setRelation('stimulus', $shared),
            )->all();
        })->values();
    }

    /**
     * Saves the questions, each stimulus first; a question whose text is
     * already there (or earlier in the same import) is left out — and a
     * stimulus's questions only all together, when every one of them is.
     *
     * @param  Collection<int, Model>  $questions  What build() returned.
     * @param  Collection<string, mixed>  $alreadyThere  The texts already there, as keys.
     * @param  Closure(Model): void  $save  Saves one question (and anything of its own, like a CQ's parts).
     * @return int How many were added.
     */
    public static function save(Collection $questions, Collection $alreadyThere, Closure $save): int
    {
        $added = 0;

        foreach (QuestionBlocks::units($questions) as $unit) {
            if (collect($unit)->every(fn (Model $question): bool => $alreadyThere->has($question->question_text))) {
                continue;
            }

            $stimulus = QuestionBlocks::stimulusOf($unit[0]);
            $stimulus?->save();

            foreach ($unit as $question) {
                $alreadyThere->put($question->question_text, true);

                if ($stimulus) {
                    $question->stimulus_id = $stimulus->id;
                    $question->unsetRelation('stimulus');
                }

                $save($question);
                $added++;
            }
        }

        return $added;
    }
}

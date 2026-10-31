<?php

namespace App\Filament\Support\Concerns;

use App\Filament\Support\QuestionBlocks;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

/**
 * A question list shown as whole-question cards by default, with a button
 * to switch to the table's columns and back — the choice kept in the URL
 * (`?view=table`), so it survives a reload. The page says where its list
 * is (displayUrl()) and what a card holds; a list of the question bank's or
 * a past paper's questions takes the one shared card (questionCardColumn()),
 * numbered, with a shared stimulus once above its questions.
 */
trait SwitchesCardAndTableView
{
    /**
     * 'cards' or 'table'.
     */
    #[Url(as: 'view')]
    public string $display = 'cards';

    /**
     * The records $questionCards was worked out for (see questionCard()).
     */
    private mixed $questionCardsAreFor = null;

    /**
     * @var array<int, array{number: int, stimulus: Model|null, heading: string|null}>
     */
    private array $questionCards = [];

    /**
     * This page in the given display: 'cards' or 'table'.
     */
    abstract protected function displayUrl(string $display): string;

    public function showsCards(): bool
    {
        return $this->display !== 'table';
    }

    /**
     * What a question's card shows around the question: its number in the
     * whole list, across pages, and — on the first card of a stimulus's
     * questions on this page — that stimulus with the heading naming their
     * numbers.
     *
     * @return array{number: int, stimulus: Model|null, heading: string|null}
     */
    public function questionCard(Model $question): array
    {
        $records = $this->getTableRecords();

        // Asked once per card: worked out once per set of records.
        if ($this->questionCardsAreFor !== $records) {
            $this->questionCardsAreFor = $records;
            $this->questionCards = [];

            // While reordering, the table holds every question, without pages.
            $isPaged = $records instanceof Paginator;

            foreach (QuestionBlocks::blocks($isPaged ? $records->items() : $records, $isPaged ? $records->firstItem() : 1) as $block) {
                foreach ($block['questions'] as $place => ['number' => $number, 'question' => $listed]) {
                    $this->questionCards[$listed->getKey()] = [
                        'number' => $number,
                        'stimulus' => $place === 0 ? $block['stimulus'] : null,
                        'heading' => $place === 0 ? $block['heading'] : null,
                    ];
                }
            }
        }

        return $this->questionCards[$question->getKey()];
    }

    protected function questionCardColumn(): View
    {
        return View::make('filament.support.questions.question-table-card');
    }

    protected function switchDisplayAction(): Action
    {
        $toTable = $this->showsCards();

        return Action::make('switchDisplay')
            ->label($toTable ? __('Table view') : __('Card view'))
            ->icon($toTable ? Heroicon::OutlinedTableCells : Heroicon::OutlinedSquares2x2)
            ->color('gray')
            ->url($this->displayUrl($toTable ? 'table' : 'cards'));
    }
}

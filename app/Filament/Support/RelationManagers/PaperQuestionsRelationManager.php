<?php

namespace App\Filament\Support\RelationManagers;

use App\Enums\QuestionType;
use App\Filament\Support\DuplicateQuestionCheck;
use App\Filament\Support\EditorModePreference;
use App\Filament\Support\QuestionJsonImportAction;
use App\Models\BoardQuestionPaper;
use App\Services\DuplicateMatch;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * A past paper's MCQs or CQs, on the paper's own page, one question at a
 * time — each added and edited in its own form (the question bank's
 * editors), never the whole paper in one form. The same list for the
 * Admin's and a Staff member's paper; who may change it is the paper's to
 * say (BoardQuestionPaper::isEditableBy()).
 *
 * A subclass says which list it is and how one of its questions is written
 * and stored.
 */
abstract class PaperQuestionsRelationManager extends RelationManager
{
    /**
     * @return array<Component>
     */
    abstract protected function formComponents(): array;

    /**
     * Which kind of question this list holds.
     */
    abstract protected function questionType(): QuestionType;

    /**
     * The form's state as a row of this list (and anything stored beside it).
     *
     * @param  array<string, mixed>  $data
     */
    abstract protected function createQuestion(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     */
    abstract protected function updateQuestion(Model $record, array $data): Model;

    /**
     * A stored question as its form shows it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract protected function fillForm(Model $record, array $data): array;

    /**
     * Header buttons a list adds beside "new question" and "Add from JSON".
     *
     * @return array<int, Action>
     */
    protected function extraHeaderActions(): array
    {
        return [];
    }

    /**
     * Row buttons a list adds.
     *
     * @return array<int, Action>
     */
    protected function extraRecordActions(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->formComponents(),
            DuplicateQuestionCheck::field(fn (Get $get, ?Model $record): ?DuplicateMatch => DuplicateQuestionCheck::onPaper($this->paper(), $this->questionType(), $get, $record)),
        ]);
    }

    public function isReadOnly(): bool
    {
        return ! $this->paper()->isEditableBy(Auth::user());
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('order_index')
            ->defaultSort('order_index')
            ->columns([
                TextColumn::make('order_index')->label('#')->rowIndex(),
                TextColumn::make('question_text')
                    ->label(__('Question'))
                    ->state(fn (Model $record): string => Str::limit(trim(html_entity_decode(strip_tags($record->question_text))), 120))
                    // Which questions share a stimulus.
                    ->description(fn (Model $record): ?string => $record->getAttribute('stimulus_id')
                        ? __('Stimulus: :text', ['text' => Str::limit(trim(html_entity_decode(strip_tags($record->stimulus->text))), 60)])
                        : null)
                    ->wrap(),
                TextColumn::make('marks')->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->using(fn (array $data): Model => $this->saved($this->createQuestion([
                        ...$data,
                        'order_index' => (int) $this->getRelationship()->max('order_index') + 1,
                    ]), $data)),
                // Many at once, as on a chapter's question list.
                QuestionJsonImportAction::forPastPaper($this->paper(), $this->questionType()),
                ...$this->extraHeaderActions(),
            ])
            ->recordActions([
                ...$this->extraRecordActions(),
                // A question under a shared stimulus is edited with its group.
                EditAction::make()
                    ->hidden(fn (Model $record): bool => $record->getAttribute('stimulus_id') !== null)
                    ->modalWidth(Width::SevenExtraLarge)
                    ->mutateRecordDataUsing(fn (Model $record, array $data): array => $this->fillForm($record, $data))
                    ->using(fn (Model $record, array $data): Model => $this->saved($this->updateQuestion($record, $data), $data)),
                DeleteAction::make()
                    ->iconButton()
                    ->after(fn () => $this->paper()->markEditedBy(Auth::user())),
            ]);
    }

    protected function paper(): BoardQuestionPaper
    {
        /** @var BoardQuestionPaper */
        return $this->getOwnerRecord();
    }

    /**
     * After any question is saved: the editor used is remembered for the
     * next question, and a Staff member's rejected paper goes back for
     * review.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saved(Model $question, array $data): Model
    {
        $this->noteChange($data);

        return $question;
    }

    /**
     * What any change to the paper's questions is followed by (see saved()).
     *
     * @param  array<string, mixed>  $data
     */
    protected function noteChange(array $data): void
    {
        EditorModePreference::rememberFromForm(Auth::user(), $data['editor_mode'] ?? null);
        $this->paper()->markEditedBy(Auth::user());
    }
}

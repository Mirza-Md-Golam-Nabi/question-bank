<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\BrowseChaptersPage;
use App\Models\Chapter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class BrowseChapters extends BrowseChaptersPage
{
    protected static string $resource = QuestionResource::class;

    public function canManageContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createChapterAction(),
        ];
    }

    public function createChapterAction(): Action
    {
        return Action::make('createChapter')
            ->label(__('Add chapter'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->unique(Chapter::class, modifyRuleUsing: fn ($rule) => $rule->where('class_subject_id', $this->classSubject->id)),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->default(fn (): int => (Chapter::where('class_subject_id', $this->classSubject->id)->max('order_index') ?? 0) + 1)
                    ->required(),
            ])
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('createAnother', arguments: ['another' => true])
                    ->label(__('Add & add another')),
            ])
            ->action(function (array $data, array $arguments, Action $action, Schema $schema): void {
                DB::transaction(function () use ($data) {
                    Chapter::reorder(
                        Chapter::where('class_subject_id', $this->classSubject->id),
                        (int) $data['order_index'],
                    );
                    Chapter::create([
                        ...$data,
                        'class_subject_id' => $this->classSubject->id,
                    ]);
                });

                if ($arguments['another'] ?? false) {
                    // Keep the modal open with a blank form; the display
                    // order default is recalculated to the next free slot.
                    $schema->fill();

                    $action->halt();
                }
            });
    }

    public function editChapterAction(): Action
    {
        return Action::make('editChapter')
            ->schema(fn (array $arguments): array => [
                TextInput::make('name')
                    ->required()
                    ->unique(
                        Chapter::class,
                        modifyRuleUsing: fn ($rule) => $rule
                            ->where('class_subject_id', $this->classSubject->id)
                            ->ignore($arguments['chapter']),
                    ),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => Chapter::findOrFail($arguments['chapter'])->only(['name', 'order_index']))
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments) {
                    $chapter = Chapter::findOrFail($arguments['chapter']);
                    Chapter::reorder(
                        Chapter::where('class_subject_id', $chapter->class_subject_id)
                            ->where('id', '!=', $chapter->id),
                        (int) $data['order_index'],
                        $chapter->order_index,
                    );
                    $chapter->update($data);
                });
            });
    }

    public function deleteChapterAction(): Action
    {
        return Action::make('deleteChapter')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => Chapter::findOrFail($arguments['chapter'])->delete());
    }
}

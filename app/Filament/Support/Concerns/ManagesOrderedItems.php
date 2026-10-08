<?php

namespace App\Filament\Support\Concerns;

use App\Models\Concerns\HasOrderIndex;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The add / edit / delete actions of the Admin's content browser pages
 * (classes, chapters, topics). Each of those is a named item with a
 * `order_index` inside some parent — chapters within a class subject,
 * topics within a chapter, classes within nothing — so the three pages
 * share one implementation and only say which model and which parent.
 *
 * `$scope` is that parent as column => value (e.g. `['chapter_id' => 5]`,
 * or `[]` for classes): it bounds the name-uniqueness rule and the order
 * sequence, and is filled in on newly created items. The model must use
 * {@see HasOrderIndex}.
 */
trait ManagesOrderedItems
{
    private const ADD_ANOTHER_SHORTCUT_HANDLER = 'if (! $event.repeat) { $wire.callMountedAction({ another: true }) }';

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     * @param  bool  $allowsAddingAnother  Adds an "Add & add another" button (also Ctrl/Cmd+Enter) that keeps the modal open for the next item.
     */
    protected function createOrderedItemAction(string $name, string $label, string $model, array $scope = [], bool $allowsAddingAnother = false): Action
    {
        $action = Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                $this->orderedItemNameField($model, $scope),
                $this->orderedItemOrderField()
                    ->default(fn (): int => ($this->orderedItemsQuery($model, $scope)->max('order_index') ?? 0) + 1),
            ])
            ->action(function (array $data, array $arguments, Action $action, Schema $schema) use ($model, $scope): void {
                DB::transaction(function () use ($data, $model, $scope) {
                    $model::reorder($this->orderedItemsQuery($model, $scope), (int) $data['order_index']);
                    $model::create([...$data, ...$scope]);
                });

                if ($arguments['another'] ?? false) {
                    // Keep the modal open with a blank form; the display
                    // order default is recalculated to the next free slot.
                    $schema->fill();

                    $action->halt();
                }
            });

        if (! $allowsAddingAnother) {
            return $action;
        }

        return $action
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('createAnother', arguments: ['another' => true])
                    ->label(__('Add & add another')),
            ])
            // Ctrl+Enter (Cmd+Enter on macOS) triggers "Add & add another";
            // plain Enter still submits the form normally. Bound on the modal
            // window rather than through ->keyBindings(), whose generated
            // element id is lost when the modal re-renders after each add.
            ->extraModalWindowAttributes([
                'x-on:keydown.ctrl.enter.prevent.stop' => self::ADD_ANOTHER_SHORTCUT_HANDLER,
                'x-on:keydown.meta.enter.prevent.stop' => self::ADD_ANOTHER_SHORTCUT_HANDLER,
            ]);
    }

    /**
     * @param  string  $argument  The action argument carrying the item's id (e.g. "chapter").
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    protected function editOrderedItemAction(string $name, string $argument, string $model, array $scope = []): Action
    {
        return Action::make($name)
            ->schema(fn (array $arguments): array => [
                $this->orderedItemNameField($model, $scope, ignoreId: $arguments[$argument]),
                $this->orderedItemOrderField(),
            ])
            ->fillForm(fn (array $arguments): array => $model::findOrFail($arguments[$argument])->only(['name', 'order_index']))
            ->action(function (array $data, array $arguments) use ($argument, $model, $scope): void {
                DB::transaction(function () use ($data, $arguments, $argument, $model, $scope) {
                    $item = $model::findOrFail($arguments[$argument]);

                    $model::reorder(
                        $this->orderedItemsQuery($model, $scope)->whereKeyNot($item->getKey()),
                        (int) $data['order_index'],
                        $item->order_index,
                    );

                    $item->update($data);
                });
            });
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function deleteOrderedItemAction(string $name, string $argument, string $model): Action
    {
        return Action::make($name)
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => $model::findOrFail($arguments[$argument])->delete());
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    private function orderedItemsQuery(string $model, array $scope): Builder
    {
        return $model::query()->where($scope);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    private function orderedItemNameField(string $model, array $scope, int|string|null $ignoreId = null): Component
    {
        return TextInput::make('name')
            ->required()
            ->unique($model, modifyRuleUsing: function ($rule) use ($scope, $ignoreId) {
                foreach ($scope as $column => $value) {
                    $rule->where($column, $value);
                }

                return $ignoreId === null ? $rule : $rule->ignore($ignoreId);
            });
    }

    private function orderedItemOrderField(): TextInput
    {
        return TextInput::make('order_index')
            ->label(__('Display order'))
            ->numeric()
            ->required();
    }
}

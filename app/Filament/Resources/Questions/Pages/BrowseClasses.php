<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\BrowseClassesPage;
use App\Models\AcademicClass;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class BrowseClasses extends BrowseClassesPage
{
    protected static string $resource = QuestionResource::class;

    public function canManageContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createClassAction(),
        ];
    }

    public function createClassAction(): Action
    {
        return Action::make('createClass')
            ->label('Add class')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->unique(AcademicClass::class),
                TextInput::make('order_index')
                    ->label('Display order')
                    ->numeric()
                    ->default(fn () => (AcademicClass::max('order_index') ?? 0) + 1)
                    ->required(),
            ])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data) {
                    AcademicClass::reorder(AcademicClass::query(), (int) $data['order_index']);
                    AcademicClass::create($data);
                });
            });
    }

    public function editClassAction(): Action
    {
        return Action::make('editClass')
            ->schema(fn (array $arguments): array => [
                TextInput::make('name')
                    ->required()
                    ->unique(AcademicClass::class, modifyRuleUsing: fn ($rule) => $rule->ignore($arguments['class'])),
                TextInput::make('order_index')
                    ->label('Display order')
                    ->numeric()
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => AcademicClass::findOrFail($arguments['class'])->only(['name', 'order_index']))
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments) {
                    $class = AcademicClass::findOrFail($arguments['class']);
                    AcademicClass::reorder(
                        AcademicClass::query()->where('id', '!=', $class->id),
                        (int) $data['order_index'],
                        $class->order_index,
                    );
                    $class->update($data);
                });
            });
    }

    public function deleteClassAction(): Action
    {
        return Action::make('deleteClass')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => AcademicClass::findOrFail($arguments['class'])->delete());
    }
}

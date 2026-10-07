<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Pages\Questions\BrowseSubjectsPage;
use App\Models\ClassSubject;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class BrowseSubjects extends BrowseSubjectsPage
{
    protected static string $resource = QuestionResource::class;

    public function canManageContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->attachSubjectAction(),
        ];
    }

    public function attachSubjectAction(): Action
    {
        return Action::make('attachSubject')
            ->label(__('Attach subject'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('subject_ids')
                    ->label(__('Subjects'))
                    ->helperText(__('Pick one or more subjects. They are added in the order you pick them, starting from the display order below.'))
                    ->options(fn (): array => Subject::query()
                        ->whereNotIn('id', $this->class->subjects()->pluck('subjects.id'))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Subject $subject): array => [$subject->id => $subject->display_name])
                        ->all())
                    ->multiple()
                    ->searchable()
                    ->required(),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->default(fn (): int => (ClassSubject::where('academic_class_id', $this->class->id)->max('order_index') ?? 0) + 1)
                    ->required(),
            ])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data) {
                    foreach (array_values($data['subject_ids']) as $offset => $subjectId) {
                        $orderIndex = (int) $data['order_index'] + $offset;

                        ClassSubject::reorder(
                            ClassSubject::where('academic_class_id', $this->class->id),
                            $orderIndex,
                        );
                        $this->class->subjects()->attach($subjectId, ['order_index' => $orderIndex]);
                    }
                });
            });
    }

    public function editSubjectOrderAction(): Action
    {
        return Action::make('editSubjectOrder')
            ->label(__('Change order'))
            ->schema([
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => [
                'order_index' => ClassSubject::findOrFail($arguments['classSubject'])->order_index,
            ])
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments) {
                    $classSubject = ClassSubject::findOrFail($arguments['classSubject']);
                    ClassSubject::reorder(
                        ClassSubject::where('academic_class_id', $classSubject->academic_class_id)
                            ->where('id', '!=', $classSubject->id),
                        (int) $data['order_index'],
                        $classSubject->order_index,
                    );
                    $classSubject->update($data);
                });
            });
    }

    public function detachSubjectAction(): Action
    {
        return Action::make('detachSubject')
            ->label(__('Remove'))
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => ClassSubject::findOrFail($arguments['classSubject'])->delete());
    }
}

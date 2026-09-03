<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Models\AcademicClass;
use App\Models\ClassSubject;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BrowseSubjects extends Page
{
    protected static string $resource = QuestionResource::class;

    protected string $view = 'filament.resources.questions.pages.browse-subjects';

    public AcademicClass|int|string $class;

    public function mount(int|string $class): void
    {
        $this->class = AcademicClass::findOrFail($class);
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->class->name} — Subjects";
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
            ->label('Attach subject')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn (): array => Subject::query()
                        ->whereNotIn('id', $this->class->subjects()->pluck('subjects.id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('order_index')
                    ->label('Display order')
                    ->numeric()
                    ->default(fn (): int => (ClassSubject::where('academic_class_id', $this->class->id)->max('order_index') ?? 0) + 1)
                    ->required(),
            ])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data) {
                    ClassSubject::reorder(
                        ClassSubject::where('academic_class_id', $this->class->id),
                        (int) $data['order_index'],
                    );
                    $this->class->subjects()->attach($data['subject_id'], ['order_index' => $data['order_index']]);
                });
            });
    }

    public function editSubjectOrderAction(): Action
    {
        return Action::make('editSubjectOrder')
            ->label('Change order')
            ->schema([
                TextInput::make('order_index')
                    ->label('Display order')
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
            ->label('Remove')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => ClassSubject::findOrFail($arguments['classSubject'])->delete());
    }

    public function subjects(): Collection
    {
        return ClassSubject::query()
            ->where('academic_class_id', $this->class->id)
            ->with('subject')
            ->withCount([
                'chapters',
                'questions as questions_count' => fn ($query) => $query->where('is_latest', true),
            ])
            ->ordered()
            ->get();
    }
}

<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BrowseChapters extends Page
{
    protected static string $resource = QuestionResource::class;

    protected string $view = 'filament.resources.questions.pages.browse-chapters';

    public AcademicClass|int|string $class;

    public ClassSubject|int|string $classSubject;

    public function mount(int|string $class, int|string $classSubject): void
    {
        $this->class = AcademicClass::findOrFail($class);
        $this->classSubject = ClassSubject::with('subject')
            ->where('academic_class_id', $this->class->id)
            ->findOrFail($classSubject);
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->class->name} · {$this->classSubject->subject->name} — Chapters";
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
            ->label('Add chapter')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->unique(Chapter::class, modifyRuleUsing: fn ($rule) => $rule->where('class_subject_id', $this->classSubject->id)),
                TextInput::make('order_index')
                    ->label('Display order')
                    ->numeric()
                    ->default(fn (): int => (Chapter::where('class_subject_id', $this->classSubject->id)->max('order_index') ?? 0) + 1)
                    ->required(),
            ])
            ->action(function (array $data): void {
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
                    ->label('Display order')
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

    public function chapters(): Collection
    {
        return Chapter::query()
            ->where('class_subject_id', $this->classSubject->id)
            ->withCount(['questions as questions_count' => fn ($query) => $query->where('is_latest', true)])
            ->ordered()
            ->get();
    }
}

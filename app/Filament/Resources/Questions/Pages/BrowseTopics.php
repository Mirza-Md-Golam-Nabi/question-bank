<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BrowseTopics extends Page
{
    use TranslatesPageLabels;

    protected const ADD_ANOTHER_SHORTCUT_HANDLER = 'if (! $event.repeat) { $wire.callMountedAction({ another: true }) }';

    protected static string $resource = QuestionResource::class;

    protected string $view = 'filament.resources.questions.pages.browse-topics';

    public AcademicClass|int|string $class;

    public ClassSubject|int|string $classSubject;

    public Chapter|int|string $chapter;

    public function mount(int|string $class, int|string $classSubject, int|string $chapter): void
    {
        $this->class = AcademicClass::findOrFail($class);
        $this->classSubject = ClassSubject::with('subject')
            ->where('academic_class_id', $this->class->id)
            ->findOrFail($classSubject);
        $this->chapter = Chapter::where('class_subject_id', $this->classSubject->id)
            ->findOrFail($chapter);
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->class->name} · {$this->classSubject->subject->name} · {$this->chapter->name} — ".__('Topics');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->createTopicAction(),
        ];
    }

    public function createTopicAction(): Action
    {
        return Action::make('createTopic')
            ->label(__('Add topic'))
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->unique(Topic::class, modifyRuleUsing: fn ($rule) => $rule->where('chapter_id', $this->chapter->id)),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->default(fn (): int => (Topic::where('chapter_id', $this->chapter->id)->max('order_index') ?? 0) + 1)
                    ->required(),
            ])
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
            ])
            ->action(function (array $data, array $arguments, Action $action, Schema $schema): void {
                DB::transaction(function () use ($data) {
                    Topic::reorder(
                        Topic::where('chapter_id', $this->chapter->id),
                        (int) $data['order_index'],
                    );
                    Topic::create([
                        ...$data,
                        'chapter_id' => $this->chapter->id,
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

    public function editTopicAction(): Action
    {
        return Action::make('editTopic')
            ->schema(fn (array $arguments): array => [
                TextInput::make('name')
                    ->required()
                    ->unique(
                        Topic::class,
                        modifyRuleUsing: fn ($rule) => $rule
                            ->where('chapter_id', $this->chapter->id)
                            ->ignore($arguments['topic']),
                    ),
                TextInput::make('order_index')
                    ->label(__('Display order'))
                    ->numeric()
                    ->required(),
            ])
            ->fillForm(fn (array $arguments): array => Topic::findOrFail($arguments['topic'])->only(['name', 'order_index']))
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments) {
                    $topic = Topic::findOrFail($arguments['topic']);
                    Topic::reorder(
                        Topic::where('chapter_id', $topic->chapter_id)
                            ->where('id', '!=', $topic->id),
                        (int) $data['order_index'],
                        $topic->order_index,
                    );
                    $topic->update($data);
                });
            });
    }

    public function deleteTopicAction(): Action
    {
        return Action::make('deleteTopic')
            ->color('danger')
            ->requiresConfirmation()
            ->action(fn (array $arguments) => Topic::findOrFail($arguments['topic'])->delete());
    }

    public function topics(): Collection
    {
        return Topic::query()
            ->where('chapter_id', $this->chapter->id)
            ->withCount(['questions as questions_count' => fn ($query) => $query->where('is_latest', true)])
            ->ordered()
            ->get();
    }
}

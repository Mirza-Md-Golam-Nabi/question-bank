<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\ManagesOrderedItems;
use App\Filament\Support\Concerns\NavigatesAdjacentChapters;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use App\Models\Topic;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

class BrowseTopics extends Page
{
    use ManagesOrderedItems, NavigatesAdjacentChapters, TranslatesPageLabels;

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
            ...$this->getAdjacentChapterActions(),
            $this->createTopicAction(),
        ];
    }

    protected function getAdjacentChapterUrl(Chapter $chapter): string
    {
        return static::getResource()::getUrl('topics', [
            'class' => $this->class->id,
            'classSubject' => $this->classSubject->id,
            'chapter' => $chapter->id,
        ]);
    }

    public function createTopicAction(): Action
    {
        return $this->createOrderedItemAction('createTopic', __('Add topic'), Topic::class, $this->topicScope(), allowsAddingAnother: true);
    }

    public function editTopicAction(): Action
    {
        return $this->editOrderedItemAction('editTopic', 'topic', Topic::class, $this->topicScope());
    }

    public function deleteTopicAction(): Action
    {
        return $this->deleteOrderedItemAction('deleteTopic', 'topic', Topic::class);
    }

    /**
     * Topics are named and ordered within their chapter.
     *
     * @return array<string, int>
     */
    private function topicScope(): array
    {
        return ['chapter_id' => $this->chapter->id];
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

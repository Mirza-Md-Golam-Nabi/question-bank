<?php

namespace App\Filament\Support\Pages\Questions;

use App\Models\AcademicClass;
use App\Models\Chapter;
use App\Models\ClassSubject;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chapter-browsing card grid for one class + subject — the third step of the
 * Class → Subject → Chapter drill-down, shared by every panel's
 * QuestionResource. Each card links to that chapter's question list. See
 * {@see BrowseClassesPage} for the read-only default; a panel that also
 * manages the chapters/topics tables (Admin) overrides `canManageContent()`.
 */
abstract class BrowseChaptersPage extends Page
{
    protected string $view = 'filament.support.questions.browse-chapters';

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

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl('index') => 'Classes',
            static::getResource()::getUrl('subjects', ['class' => $this->class->id]) => $this->class->name,
            "{$this->classSubject->subject->name} — Chapters",
        ];
    }

    public function canManageContent(): bool
    {
        return false;
    }

    /**
     * @return Collection<int, Chapter>
     */
    public function chapters(): Collection
    {
        return Chapter::query()
            ->where('class_subject_id', $this->classSubject->id)
            ->withCount(['questions as questions_count' => fn ($query) => $query->where('is_latest', true)])
            ->ordered()
            ->get();
    }
}

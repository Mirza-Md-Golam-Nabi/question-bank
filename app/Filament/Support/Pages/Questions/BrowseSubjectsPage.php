<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use App\Models\ClassSubject;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Subject-browsing card grid for one class — the second step of the
 * Class → Subject → Chapter drill-down, shared by every panel's
 * QuestionResource. See {@see BrowseClassesPage} for the read-only default.
 */
abstract class BrowseSubjectsPage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.support.questions.browse-subjects';

    public AcademicClass|int|string $class;

    public function mount(int|string $class): void
    {
        $this->class = AcademicClass::findOrFail($class);
    }

    public function getTitle(): string|Htmlable
    {
        return "{$this->class->name} — ".__('Subjects');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            static::getResource()::getUrl('index') => __('Classes'),
            "{$this->class->name} — ".__('Subjects'),
        ];
    }

    public function canManageContent(): bool
    {
        return false;
    }

    /**
     * @return Collection<int, ClassSubject>
     */
    public function subjects(): Collection
    {
        return ClassSubject::query()
            ->where('academic_class_id', $this->class->id)
            ->with('subject')
            ->withCount([
                'chapters',
                'questions as questions_count' => fn ($query) => static::getResource()::scopeCountedQuestions($query),
            ])
            ->ordered()
            ->get();
    }
}

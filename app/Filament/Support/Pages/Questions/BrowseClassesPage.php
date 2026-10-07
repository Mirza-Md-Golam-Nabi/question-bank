<?php

namespace App\Filament\Support\Pages\Questions;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Models\AcademicClass;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class-browsing card grid, shared by every panel's QuestionResource — the
 * `index` page of the Class → Subject → Chapter drill-down. Read-only by
 * default; only a panel whose users may manage the academic_classes table
 * (Admin) overrides `canManageContent()` and adds the create/edit/delete
 * actions on top.
 */
abstract class BrowseClassesPage extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.support.questions.browse-classes';

    public function getTitle(): string
    {
        return __('Classes');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            __('Classes'),
        ];
    }

    public function canManageContent(): bool
    {
        return false;
    }

    /**
     * @return Collection<int, AcademicClass>
     */
    public function classes(): Collection
    {
        return AcademicClass::query()
            ->withCount('classSubjects')
            ->ordered()
            ->get();
    }
}

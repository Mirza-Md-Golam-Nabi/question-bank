<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Resources\Questions\QuestionResource;
use App\Filament\Support\Concerns\ManagesOrderedItems;
use App\Filament\Support\Pages\Questions\BrowseClassesPage;
use App\Models\AcademicClass;
use Filament\Actions\Action;

class BrowseClasses extends BrowseClassesPage
{
    use ManagesOrderedItems;

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
        return $this->createOrderedItemAction('createClass', __('Add class'), AcademicClass::class);
    }

    public function editClassAction(): Action
    {
        return $this->editOrderedItemAction('editClass', 'class', AcademicClass::class);
    }

    public function deleteClassAction(): Action
    {
        return $this->deleteOrderedItemAction('deleteClass', 'class', AcademicClass::class);
    }
}

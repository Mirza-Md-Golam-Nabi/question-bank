<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->translateFilamentLabels();
        $this->registerLanguageSwitcher();
    }

    /**
     * Every label — explicit or derived from the field/column name — is
     * looked up in the active locale's translations (lang/bn.json), with the
     * English text as the key.
     */
    protected function translateFilamentLabels(): void
    {
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());
        Action::configureUsing(fn (Action $action) => $action->translateLabel());
    }

    /**
     * Shows the Bangla/English switcher in every panel: next to the user
     * menu once signed in, and under the form on the sign-in pages.
     */
    protected function registerLanguageSwitcher(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.support.language-switcher'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIMPLE_PAGE_END,
            fn (): View => view('filament.support.language-switcher', ['centered' => true]),
        );
    }
}

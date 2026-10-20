<?php

namespace App\Providers;

use App\Enums\Locale;
use App\Http\Responses\LogoutResponse;
use App\Services\ImportedImages;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use Closure;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Forms\Components\Field;
use Filament\Infolists\Components\Entry;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
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
        // No SMS provider is integrated yet — swap this binding when one is.
        $this->app->bind(SmsSender::class, LogSmsSender::class);

        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->translateFilamentLabels();
        $this->registerLanguageSwitcher();
        $this->allowImportImageUploads();
        $this->registerLocalDateColumns();
    }

    /**
     * `->localDate()` / `->localDateTime()` on a table column: Filament's
     * date()/dateTime(), with the digits in the viewer's language too — in
     * Bangla "অক্টো ২০, ২০২৬ ২৩:২১", not "অক্টো 20, 2026 23:21". Every date
     * column uses these instead of date()/dateTime().
     */
    protected function registerLocalDateColumns(): void
    {
        $inLocalDigits = function (TextColumn $column): TextColumn {
            $format = Closure::bind(fn (): ?Closure => $this->formatStateUsing, $column, TextColumn::class)();

            return $column->formatStateUsing(fn (TextColumn $column, mixed $state): ?string => blank($state)
                ? null
                : Locale::current()->writeDigits((string) $column->evaluate($format, ['state' => $state])));
        };

        TextColumn::macro('localDate', fn (?string $format = null): TextColumn => $inLocalDigits($this->date($format)));
        TextColumn::macro('localDateTime', fn (?string $format = null): TextColumn => $inLocalDigits($this->dateTime($format)));
    }

    /**
     * Livewire takes uploads of up to 12 MB on its own; a ZIP of a JSON
     * import's images may be bigger. This is only the technical ceiling —
     * how much an import may really carry is the Admin's setting, checked
     * by ImportedImages.
     */
    protected function allowImportImageUploads(): void
    {
        config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.ImportedImages::UPLOAD_CEILING_KB]]);
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

<?php

namespace App\Filament\Support\Widgets;

use App\Filament\Support\Pages\RequestReactivationPage;
use App\Models\ReactivationRequest;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

/**
 * The dashboard notice a suspended Teacher or Staff member sees, with the
 * way out of it: asking an Admin to switch the account back on. A panel's
 * own widget only says which page that request is made on.
 */
abstract class SuspensionNoticeWidget extends Widget
{
    protected string $view = 'filament.support.widgets.suspension-notice';

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return class-string<RequestReactivationPage>
     */
    abstract protected function reactivationPage(): string;

    public static function canView(): bool
    {
        return Auth::user()?->isSuspended() ?? false;
    }

    public function reactivationUrl(): string
    {
        return $this->reactivationPage()::getUrl(panel: Auth::user()->role->panelId());
    }

    public function hasPendingRequest(): bool
    {
        return ReactivationRequest::pendingFor(Auth::user()) !== null;
    }
}

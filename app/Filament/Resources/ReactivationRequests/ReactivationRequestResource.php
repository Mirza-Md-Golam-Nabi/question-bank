<?php

namespace App\Filament\Resources\ReactivationRequests;

use App\Enums\ReactivationRequestStatus;
use App\Filament\Resources\ReactivationRequests\Pages\ManageReactivationRequests;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\ReactivationRequest;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Suspended Teachers' and Staff members' requests to be switched back on,
 * for the Admin to approve or decline. A request is written by its user
 * and decided through ReactivationRequest — never typed in or edited here.
 */
class ReactivationRequestResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = ReactivationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::People;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewedBy']);
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = ReactivationRequest::where('status', ReactivationRequestStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('Date'))->dateTime()->sortable(),
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('user.role')->label(__('Role'))->badge(),
                TextColumn::make('message')->wrap(),
                TextColumn::make('status')->badge(),
                TextColumn::make('admin_note')->label(__('Note'))->placeholder('—')->wrap(),
                TextColumn::make('reviewedBy.name')->label(__('Reviewed by'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ReactivationRequestStatus::class),
            ])
            ->recordActions([
                self::reviewAction('approve', __('Reactivate'), 'success', Heroicon::OutlinedCheckCircle, __('Account reactivated.'))
                    ->modalDescription(__('The account becomes active again straight away.')),
                self::reviewAction('decline', __('Decline'), 'danger', Heroicon::OutlinedXCircle, __('Request declined.')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReactivationRequests::route('/'),
        ];
    }

    /**
     * Approve and decline differ only in which of the request's own
     * methods they call (the action's name) and how they look.
     */
    private static function reviewAction(string $name, string $label, string $color, Heroicon $icon, string $doneMessage): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->visible(fn (ReactivationRequest $record): bool => $record->isPending())
            ->requiresConfirmation()
            ->schema([
                TextInput::make('admin_note')->label(__('Note (shown to the user)'))->maxLength(255),
            ])
            ->action(fn (ReactivationRequest $record, array $data) => $record->{$name}(auth()->user(), $data['admin_note'] ?? null)
                ? Notification::make()->title($doneMessage)->success()->send()
                : Notification::make()->title(__('This request has already been handled.'))->warning()->send());
    }
}

<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\SubscriptionChangeActions;
use App\Filament\Support\TableActions;
use App\Filament\Support\UserSelect;
use App\Models\Subscription;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Every subscription. A new one is only ever given through "Give a
 * subscription" (`GrantSubscriptionAction`), which copies the plan's
 * conditions and records a payment. Here any Admin can end one early,
 * cancel or lengthen it; the Super Admin can also move it to another plan
 * or correct its classes/subjects (SubscriptionChangeActions) — what it
 * gives is never typed in by hand.
 */
class SubscriptionResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Subscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                UserSelect::make()->disabled(),
                Select::make('plan_id')
                    ->label(__('Plan'))
                    ->relationship('plan', 'name')
                    ->disabled(),
                Select::make('status')->options(SubscriptionStatus::class)->required(),
                DateTimePicker::make('ends_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('User'))
                    ->description(fn (Subscription $record): ?string => $record->user?->email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('user', fn (Builder $user) => $user->matching($search))),
                TextColumn::make('user.phone')->label(__('Phone'))->placeholder('—')->toggleable(),
                TextColumn::make('user.role')->label(__('Role'))->badge(),
                TextColumn::make('plan.name'),
                // Only a gift is marked; anything else stays empty.
                TextColumn::make('gift')
                    ->label(__('Gift'))
                    ->state(fn (Subscription $record): ?string => $record->is_gift ? __('Gift') : null)
                    ->badge()
                    ->icon(Heroicon::OutlinedGift)
                    ->color('success'),
                TextColumn::make('status')->badge(),
                TextColumn::make('starts_at')->localDateTime(),
                TextColumn::make('ends_at')->localDateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(SubscriptionStatus::class),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->searchPlaceholder(__('Name, email or phone'))
            // Whole, so a phone number typed with spaces ("+880 1711…") stays one number.
            ->splitSearchTerms(false)
            ->recordActions([
                ActionGroup::make([
                    SubscriptionChangeActions::changePlan(asSuperAdmin: true),
                    SubscriptionChangeActions::changeSelections(asSuperAdmin: true),
                ])
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->tooltip(__('Change subscription')),
                EditAction::make()->after(fn (Subscription $record) => Log::info('Subscription changed by an Admin.', [
                    'subscription_id' => $record->id,
                    'status' => $record->status->value,
                    'ends_at' => $record->ends_at?->toDateTimeString(),
                    'changed_by' => auth()->id(),
                ])),
                DeleteAction::make(),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}

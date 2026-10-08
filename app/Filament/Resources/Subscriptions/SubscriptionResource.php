<?php

namespace App\Filament\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\Pages\ManageSubscriptions;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Filament\Support\UserSelect;
use App\Models\Subscription;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                UserSelect::make(),
                Select::make('plan_id')
                    ->label(__('Plan'))
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('status')->options(SubscriptionStatus::class)->required(),
                DateTimePicker::make('starts_at')->required(),
                DateTimePicker::make('ends_at'),
                Toggle::make('auto_renew'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->searchable(),
                TextColumn::make('plan.name'),
                TextColumn::make('status')->badge(),
                TextColumn::make('starts_at')->dateTime(),
                TextColumn::make('ends_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(SubscriptionStatus::class),
            ])
            ->recordActions(TableActions::editAndDelete())
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptions::route('/'),
        ];
    }
}

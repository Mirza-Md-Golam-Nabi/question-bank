<?php

namespace App\Filament\Resources\SubscriptionPlans;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionTargetRole;
use App\Filament\Resources\SubscriptionPlans\Pages\ManageSubscriptionPlans;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionPlanResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = SubscriptionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                Select::make('target_role')->options(SubscriptionTargetRole::class)->required(),
                Select::make('billing_cycle')->options(BillingCycle::class)->required()->live(),
                TextInput::make('price')->numeric()->minValue(0)->required(),
                TextInput::make('monthly_exam_limit')
                    ->label(__('Monthly exam limit (blank = unlimited)'))
                    ->numeric()
                    ->minValue(0),
                Toggle::make('is_default_free')
                    ->label(__('Assign automatically to new sign-ups of this role')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('target_role')->badge(),
                TextColumn::make('billing_cycle')->badge(),
                TextColumn::make('price')->money('BDT'),
                TextColumn::make('monthly_exam_limit')->label(__('Monthly limit'))->placeholder(__('Unlimited')),
                IconColumn::make('is_default_free')->boolean()->label(__('Default')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptionPlans::route('/'),
        ];
    }
}

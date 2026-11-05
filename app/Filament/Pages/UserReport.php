<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\User;
use App\Services\UserSubscriptionReport;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * How many people signed in with Google, by role (a tab each, plus "All"):
 * in total, ever subscribed and never, paid, given a gift, on a trial —
 * and below, each user with how many times they subscribed, most first.
 * Every figure and the sorting come from App\Services\UserSubscriptionReport.
 */
class UserReport extends Page implements HasTable
{
    use InteractsWithTable;
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.user-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::People;

    protected static ?string $title = 'Users';

    /**
     * `all` or a Google role's value.
     */
    #[Url]
    public string $tab = 'all';

    /**
     * The tabs, keyed by what `$tab` holds.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        return ['all' => __('All')] + collect(UserSubscriptionReport::googleRoles())
            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'all';
        $this->resetPage();
    }

    /**
     * Every tab's figures, counted in one query once per request.
     *
     * @return array<string, array{total: int, subscribed: int, unsubscribed: int, paid: int, gifted: int, on_trial: int}>
     */
    public function totals(): array
    {
        return once(fn (): array => UserSubscriptionReport::totals());
    }

    /**
     * The selected tab's figures, for its cards (UserReportStatsWidget).
     *
     * @return array{total: int, subscribed: int, unsubscribed: int, paid: int, gifted: int, on_trial: int}
     */
    public function figures(): array
    {
        return $this->totals()[$this->tab] ?? $this->totals()['all'];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => (new UserSubscriptionReport(UserRole::tryFrom($this->tab)))->users())
            // Most subscriptions first; the id keeps the pages stable among equals.
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('subscriptions_count')->orderBy('users.id'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->description(fn (User $record): ?string => $record->email)
                    ->searchable(['users.name', 'users.email']),
                TextColumn::make('role')
                    ->label(__('Role'))
                    ->badge()
                    ->visible(fn (): bool => $this->tab === 'all'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (UserStatus $state): string => $state === UserStatus::Active ? 'success' : 'gray'),
                TextColumn::make('subscriptions_count')
                    ->label(__('Times subscribed'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('subscriptions_count', $direction)->orderBy('users.id')),
                TextColumn::make('paid_count')
                    ->label(__('Bought'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('paid_count', $direction)->orderBy('users.id')),
                TextColumn::make('trial_count')
                    ->label(__('Trial'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('trial_count', $direction)->orderBy('users.id')),
                TextColumn::make('gift_count')
                    ->label(__('Gift'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('gift_count', $direction)->orderBy('users.id')),
                TextColumn::make('last_subscribed_at')
                    ->label(__('Last subscribed'))
                    ->localDateTime()
                    ->placeholder('—')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('last_subscribed_at', $direction)->orderBy('users.id')),
                TextColumn::make('created_at')
                    ->label(__('Joined'))
                    ->localDate()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('users.created_at', $direction)->orderBy('users.id')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(UserStatus::class)
                    ->attribute('users.status'),
                SelectFilter::make('subscription')
                    ->label(__('Subscription'))
                    ->options(UserSubscriptionReport::subscriptionStateOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? UserSubscriptionReport::whereSubscriptionState($query, $data['value'])
                        : $query),
                $this->dateRangeFilter('joined', __('Joined'), 'users.created_at'),
                $this->dateRangeFilter('last_subscribed', __('Last subscribed'), 'subscription_totals.last_subscribed_at'),
            ])
            ->filtersFormColumns(['default' => 2])
            ->filtersFormWidth(Width::TwoExtraLarge);
    }

    /**
     * A "from – until" filter on one date column, both ends optional.
     */
    private function dateRangeFilter(string $name, string $label, string $column): Filter
    {
        return Filter::make($name)
            ->schema([
                DatePicker::make('from')->label($label.' — '.__('From')),
                DatePicker::make('until')->label($label.' — '.__('Until')),
            ])
            // "From" and "Until" side by side, a row of their own.
            ->columns(['default' => 2])
            ->columnSpanFull()
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate($column, '<=', $date)))
            ->indicateUsing(fn (array $data): array => array_values(array_filter([
                filled($data['from'] ?? null) ? $label.' — '.__('From').': '.$data['from'] : null,
                filled($data['until'] ?? null) ? $label.' — '.__('Until').': '.$data['until'] : null,
            ])));
    }
}

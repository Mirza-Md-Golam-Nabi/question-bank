<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\User;
use App\Services\ReferralReport as Report;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\WithPagination;

/**
 * The Admin's referral report: who brought in how many customers and how
 * much cash, against the rewards that cost. Every figure comes from
 * App\Services\ReferralReport; this page only holds the filters.
 */
class ReferralReport extends Page
{
    use TranslatesPageLabels;
    use WithPagination;

    protected string $view = 'filament.pages.referral-report';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $filters = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Select::make('role')
                    ->label(__('Referrer is a'))
                    ->placeholder(__('Everyone'))
                    // Whoever signs in with Google can hold a referral code.
                    ->options(collect(UserRole::cases())
                        ->reject(fn (UserRole $role): bool => $role->isPasswordAuthenticated())
                        ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                        ->all())
                    ->live(),
                DatePicker::make('from')->label(__('From'))->live(),
                DatePicker::make('until')->label(__('Until'))->live(),
            ])
            ->statePath('filters');
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function rows(): LengthAwarePaginator
    {
        return $this->report()->rows();
    }

    /**
     * @return array<string, int|float>
     */
    public function totals(): array
    {
        return $this->report()->totals();
    }

    private function report(): Report
    {
        $role = $this->filters['role'] ?? null;

        return new Report(
            $role instanceof UserRole ? $role : UserRole::tryFrom((string) $role),
            filled($this->filters['from'] ?? null) ? Carbon::parse($this->filters['from']) : null,
            filled($this->filters['until'] ?? null) ? Carbon::parse($this->filters['until']) : null,
        );
    }
}

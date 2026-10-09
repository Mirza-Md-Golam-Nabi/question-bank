<?php

namespace App\Filament\Pages;

use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\BillingSetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Where the Admin sets every billing and referral number — none of them is
 * written in the code. A change applies from the next purchase on; rewards
 * and discounts already given keep the amount they were given at.
 */
class BillingSettings extends Page
{
    use TranslatesPageLabels;

    protected string $view = 'filament.pages.billing-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(BillingSetting::current()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Receiving payments'))
                    ->description(__('The numbers customers send their payment to. A method without a number is not offered.'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('bkash_number')->label(__('bKash number'))->tel()->maxLength(20),
                        TextInput::make('nagad_number')->label(__('Nagad number'))->tel()->maxLength(20),
                        TextInput::make('rocket_number')->label(__('Rocket number'))->tel()->maxLength(20),
                        Textarea::make('payment_instructions')
                            ->label(__('Extra instructions shown to the customer'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Referral'))
                    ->columns(3)
                    ->schema([
                        Toggle::make('referral_enabled')
                            ->label(__('Referral programme is on'))
                            ->columnSpanFull(),
                        TextInput::make('referrer_reward_percent')
                            ->label(__('Teacher / Student referrer reward (%)'))
                            ->helperText(__("Share of the cash paid on the new customer's first purchase."))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('staff_reward_percent')
                            ->label(__('Staff referrer reward (%)'))
                            ->helperText(__('Paid to the Staff member in cash with their payout, so it is set apart from the wallet-credit reward.'))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('referee_discount_percent')
                            ->label(__('New customer discount (%)'))
                            ->helperText(__("Taken off the new customer's first purchase."))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('credit_expiry_months')
                            ->label(__('Wallet credit expires after (months)'))
                            ->helperText(__('Blank = never expires.'))
                            ->integer()->minValue(1),
                    ]),

                Section::make(__('Refunds'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('refund_window_days')
                            ->label(__('Refund period (days)'))
                            ->helperText(__('A payment can be refunded for this many days after approval, and a referral reward only becomes available once they have passed. 0 = no refunds; rewards are available at once.'))
                            ->integer()->minValue(0)->required()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Phone number'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('phone_bonus_exams')
                            ->label(__('Extra free exams per month for giving a phone number'))
                            ->integer()->minValue(0)->required(),
                        Toggle::make('otp_required_for_credit')
                            ->label(__('Ask for an OTP when wallet credit is spent'))
                            ->helperText(__('Turn on only after an SMS provider is connected — until then no code reaches the phone.'))
                            ->columnSpan(2),
                    ]),

                Section::make(__('Question bank'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('question_import_max')
                            ->label(__('Most questions in one JSON import'))
                            ->helperText(__('How many questions a Teacher or an Admin can add at once by pasting JSON.'))
                            ->integer()->minValue(1)->maxValue(1000)->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = BillingSetting::current();
        $settings->fill($this->form->getState())->save();

        // These numbers decide where money goes and how much credit is
        // given, so who changed them is kept on record.
        Log::info('Billing settings changed.', [
            'changed_by' => Auth::id(),
            'changes' => $settings->getChanges(),
        ]);

        Notification::make()->title(__('Settings saved'))->success()->send();
    }
}

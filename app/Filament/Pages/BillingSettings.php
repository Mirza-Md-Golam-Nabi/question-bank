<?php

namespace App\Filament\Pages;

use App\Enums\AdminNotificationTopic;
use App\Enums\PaperSourceType;
use App\Filament\Support\Concerns\TranslatesPageLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\BillingSetting;
use App\Models\User;
use Filament\Forms\Components\Select;
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
        // Teacher and Student commission work the same way; only the
        // percentage differs.
        $commissionHelp = __('Share of the cash a referred buyer pays. Only earned while the referrer has a paid subscription; goes to their wallet and can be taken out as cash above the reserve.');

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
                        TextInput::make('teacher_reward_percent')
                            ->label(__('Teacher commission (%)'))
                            ->helperText($commissionHelp)
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('student_reward_percent')
                            ->label(__('Student commission (%)'))
                            ->helperText($commissionHelp)
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('staff_reward_percent')
                            ->label(__('Staff referrer reward (%)'))
                            ->helperText(__('Paid to the Staff member in cash with their payout.'))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        Toggle::make('commission_first_purchase_only')
                            ->label(__('Reward only the first purchase under a referral'))
                            ->helperText(__('Off: every purchase a student makes while under a referral earns the referrer a reward. A referred teacher only ever earns their referrer one, on their first purchase.'))
                            ->columnSpanFull(),
                        TextInput::make('referee_discount_percent')
                            ->label(__('Discount for a referred buyer (%)'))
                            ->helperText(__("Taken off every purchase of a student under a referral, and off a referred teacher's first purchase. 0 = no discount."))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                        TextInput::make('referral_lock_days')
                            ->label(__('A student stays under a referrer for (days)'))
                            ->helperText(__('Counted from the day it was set. After that it ends by itself, and the next code or exam link sets a new one.'))
                            ->integer()->minValue(1)->required(),
                        TextInput::make('referral_change_days')
                            ->label(__('A typed code can be changed for (days)'))
                            ->helperText(__("0 = it can't be changed. A referral set by opening a teacher's exam link is fixed at once."))
                            ->integer()->minValue(0)->required(),
                    ]),

                Section::make(__('Taking commission out'))
                    ->description(__("What stays in the wallet can only be spent on the user's own next subscription, never taken out as cash. Wallet money never expires."))
                    ->columns(2)
                    ->schema([
                        TextInput::make('teacher_reserve_amount')
                            ->label(__("Always kept in a teacher's wallet (৳)"))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('teacher_min_withdrawal')
                            ->label(__('Smallest withdrawal for a teacher (৳)'))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('student_reserve_amount')
                            ->label(__("Always kept in a student's wallet (৳)"))
                            ->numeric()->minValue(0)->required(),
                        TextInput::make('student_min_withdrawal')
                            ->label(__('Smallest withdrawal for a student (৳)'))
                            ->numeric()->minValue(0)->required(),
                    ]),

                Section::make(__('Group plans'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('group_member_change_days')
                            ->label(__('Someone can be taken off a group plan once every (days)'))
                            ->helperText(__("So a few seats can't be passed round a whole class."))
                            ->integer()->minValue(0)->required(),
                    ]),

                Section::make(__('Devices'))
                    ->description(__('How many devices one account can be signed in on at once. Signing in on one more signs out the one used longest ago. 0 = no limit.'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('teacher_device_limit')
                            ->label(__('Devices per teacher'))
                            ->integer()->minValue(0)->required(),
                        TextInput::make('student_device_limit')
                            ->label(__('Devices per student'))
                            ->integer()->minValue(0)->required(),
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

                // Which Admins hear of what, one line per kind of
                // notification (AdminNotificationTopic).
                Section::make(__('Notifications for Admins'))
                    ->description(__('Who among the Admins gets each kind of notification in their bell. Nobody chosen: every Admin.'))
                    ->schema(collect(AdminNotificationTopic::cases())
                        ->map(fn (AdminNotificationTopic $topic): Select => Select::make("admin_notification_recipients.{$topic->value}")
                            ->label($topic->getLabel())
                            ->helperText($topic->getDescription())
                            ->multiple()
                            ->options(fn (): array => User::query()
                                ->admins()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->placeholder(__('Every Admin')))
                        ->all()),

                Section::make(__('Question bank'))
                    ->columns(3)
                    ->schema([
                        Toggle::make('teacher_question_import_enabled')
                            ->label(__('Teachers can add questions from JSON'))
                            ->helperText(__('When off, only an Admin sees the "Add from JSON" button.'))
                            ->columnSpanFull(),
                        Toggle::make('staff_paper_import_enabled')
                            ->label(__('Staff can add past paper questions from JSON'))
                            ->helperText(__('When on, every Staff member gets the "Add from JSON" button on their past papers; when off, none does. An Admin always has it.'))
                            ->columnSpanFull(),
                        TextInput::make('question_import_max')
                            ->label(__('Most questions in one JSON import'))
                            ->helperText(__('How many questions can be added at once by pasting JSON — to a chapter or to a past paper.'))
                            ->integer()->minValue(1)->maxValue(1000)->required(),
                        TextInput::make('question_import_image_max_kb')
                            ->label(__('Largest image in a JSON import (KB)'))
                            ->helperText(__('Each image given with the JSON — one by one or inside a ZIP. A larger one is listed as not taken.'))
                            ->integer()->minValue(50)->maxValue(10240)->required(),
                        TextInput::make('question_import_images_max_mb')
                            ->label(__('All images of one JSON import together (MB)'))
                            ->helperText(__('What the images add up to (a ZIP counts by what is inside it).'))
                            ->integer()->minValue(1)->maxValue(50)->required(),
                        TextInput::make('duplicate_similarity_percent')
                            ->label(__('Possible duplicate from (%)'))
                            ->helperText(__('A new question this alike to one already in the chapter (or the past paper) — its text and its options each — is shown as a possible duplicate, for whoever adds it to decide. An identical one is never added.'))
                            ->integer()->minValue(50)->maxValue(99)->required(),
                    ]),

                // One amount per kind of paper, paid when an Admin approves a
                // Staff member's paper — snapshotted then, like question rates.
                Section::make(__('Staff pay per past paper'))
                    ->description(__('What a Staff member earns for each of their past papers an Admin approves.'))
                    ->columns(3)
                    ->schema(collect(PaperSourceType::cases())
                        ->map(fn (PaperSourceType $type) => TextInput::make("paper_rates.{$type->value}")
                            ->label($type->getLabel())
                            ->prefix('৳')
                            ->numeric()
                            ->minValue(0)
                            ->default(0))
                        ->all()),

                Section::make(__('Question difficulty'))
                    ->description(__('The difficulty of an MCQ is set from how students answer it on teacher exams, once enough of them have.'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('difficulty_min_answers')
                            ->label(__('Answers needed before the difficulty is calculated'))
                            ->helperText(__('Until then the question keeps the difficulty its author chose.'))
                            ->integer()->minValue(1)->required(),
                        TextInput::make('difficulty_hard_max_percent')
                            ->label(__('Hard: up to this % answer correctly'))
                            ->numeric()->minValue(0)->maxValue(100)->required()
                            ->lt('difficulty_medium_max_percent'),
                        TextInput::make('difficulty_medium_max_percent')
                            ->label(__('Medium: up to this % answer correctly'))
                            ->helperText(__('Above this the question is Easy.'))
                            ->numeric()->minValue(0)->maxValue(100)->required(),
                    ]),

                Section::make(__('Negative marking'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('default_negative_marks')
                            ->label(__('Marks taken off per wrong answer, to start with'))
                            ->helperText(__("What a teacher's negative-marking box starts on when saving an online exam. The teacher can change it per exam."))
                            ->numeric()->minValue(0.01)->maxValue(100)->required(),
                    ]),

                Section::make(__('Practising mistakes'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('mistake_clear_streak')
                            ->label(__('Correct answers in a row that clear a mistake'))
                            ->helperText(__('A question a student got wrong leaves their mistakes list once they have answered it correctly this many times in a row.'))
                            ->integer()->minValue(1)->maxValue(20)->required(),
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

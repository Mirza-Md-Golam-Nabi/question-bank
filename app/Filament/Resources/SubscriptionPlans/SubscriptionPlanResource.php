<?php

namespace App\Filament\Resources\SubscriptionPlans;

use App\Enums\Difficulty;
use App\Enums\PlanConditionType;
use App\Enums\PlanDuration;
use App\Enums\PlanFeature;
use App\Enums\SubscriptionTargetRole;
use App\Filament\Resources\SubscriptionPlans\Pages\ManageSubscriptionPlans;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Models\AcademicClass;
use App\Models\Subject;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The Admin's price list (CLAUDE.md rule 7). A plan is a price, a length,
 * and whatever conditions the Admin adds to it — each condition is a block
 * that can be added and removed; what each block's data means is
 * PlanConditions'. Editing a plan never changes subscriptions already
 * bought: they keep a copy of the conditions they were bought with.
 */
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
                Section::make(__('Plan'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')->required(),
                        Select::make('target_role')->options(SubscriptionTargetRole::class)->required(),
                        TextInput::make('price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('৳')
                            ->required(),
                        Grid::make(2)->schema([
                            Select::make('duration_unit')
                                ->label(__('Lasts'))
                                ->options(PlanDuration::class)
                                ->default(PlanDuration::Months)
                                ->required()
                                ->live(),
                            TextInput::make('duration_length')
                                ->label(__('How many'))
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->default(1)
                                ->visible(fn (Get $get): bool => self::durationFrom($get('duration_unit'))?->needsLength() ?? false)
                                ->required(fn (Get $get): bool => self::durationFrom($get('duration_unit'))?->needsLength() ?? false),
                            DatePicker::make('ends_on')
                                ->label(__('Ends on'))
                                ->helperText(__('For an "until the exam" plan — every subscription ends on this date.'))
                                ->visible(fn (Get $get): bool => self::durationFrom($get('duration_unit')) === PlanDuration::UntilDate)
                                ->required(fn (Get $get): bool => self::durationFrom($get('duration_unit')) === PlanDuration::UntilDate),
                        ]),
                        Toggle::make('is_active')
                            ->label(__('On sale'))
                            ->default(true),
                        Toggle::make('is_default_free')
                            ->label(__('Free plan for everyone of this role')),
                        Toggle::make('is_trial')
                            ->label(__('Free trial (once per account)')),
                    ]),

                Section::make(__('Conditions'))
                    ->description(__('Add only what limits this plan. Anything without a condition is not limited (no Classes condition = every class).'))
                    ->columnSpanFull()
                    ->schema([
                        Builder::make('conditions')
                            ->hiddenLabel()
                            ->addActionLabel(__('Add condition'))
                            ->blockPickerColumns(2)
                            ->collapsible()
                            ->blocks(self::conditionBlocks()),
                    ]),
            ]);
    }

    /**
     * One block per PlanConditionType, each at most once per plan.
     *
     * @return array<int, Block>
     */
    private static function conditionBlocks(): array
    {
        return [
            Block::make(PlanConditionType::Classes->value)
                ->label(PlanConditionType::Classes->getLabel())
                ->icon(Heroicon::OutlinedAcademicCap)
                ->maxItems(1)
                ->columns(2)
                ->schema([
                    Select::make('class_ids')
                        ->label(__('Classes'))
                        ->helperText(__('Leave empty for every class.'))
                        ->options(fn (): array => AcademicClass::options())
                        ->multiple(),
                    TextInput::make('pick_count')
                        ->label(__('Buyer picks how many'))
                        ->helperText(__('Leave empty to give all of the listed classes.'))
                        ->numeric()
                        ->integer()
                        ->minValue(1),
                ]),

            Block::make(PlanConditionType::Subjects->value)
                ->label(PlanConditionType::Subjects->getLabel())
                ->icon(Heroicon::OutlinedBookOpen)
                ->maxItems(1)
                ->columns(2)
                ->schema([
                    Select::make('subject_ids')
                        ->label(__('Subjects'))
                        ->helperText(__('Leave empty for every subject.'))
                        ->options(fn () => Subject::query()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable(),
                    TextInput::make('pick_count')
                        ->label(__('Buyer picks how many'))
                        ->helperText(__('Leave empty to give all of the listed subjects.'))
                        ->numeric()
                        ->integer()
                        ->minValue(1),
                ]),

            Block::make(PlanConditionType::Difficulties->value)
                ->label(PlanConditionType::Difficulties->getLabel())
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->maxItems(1)
                ->schema([
                    CheckboxList::make('difficulties')
                        ->label(__('Allowed difficulties'))
                        ->options(Difficulty::class)
                        ->columns(3)
                        ->required(),
                ]),

            Block::make(PlanConditionType::Features->value)
                ->label(PlanConditionType::Features->getLabel())
                ->icon(Heroicon::OutlinedSparkles)
                ->maxItems(1)
                ->schema([
                    CheckboxList::make('features')
                        ->label(__('Included'))
                        ->helperText(__('Anything not ticked is not part of the plan.'))
                        ->options(PlanFeature::class)
                        ->columns(2),
                ]),

            Block::make(PlanConditionType::DailyExamLimit->value)
                ->label(PlanConditionType::DailyExamLimit->getLabel())
                ->icon(Heroicon::OutlinedSun)
                ->maxItems(1)
                ->schema([
                    TextInput::make('limit')
                        ->label(__('Exams per day'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->required(),
                ]),

            Block::make(PlanConditionType::MonthlyExamLimit->value)
                ->label(PlanConditionType::MonthlyExamLimit->getLabel())
                ->icon(Heroicon::OutlinedCalendarDays)
                ->maxItems(1)
                ->schema([
                    TextInput::make('limit')
                        ->label(__('Exams per month'))
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->required(),
                ]),

            Block::make(PlanConditionType::MaxParticipants->value)
                ->label(PlanConditionType::MaxParticipants->getLabel())
                ->icon(Heroicon::OutlinedUserGroup)
                ->maxItems(1)
                ->schema([
                    TextInput::make('limit')
                        ->label(__('Most people who can sit one online exam'))
                        ->helperText(__('For a teacher plan: after this many, the exam link takes no one new.'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                ]),

            Block::make(PlanConditionType::Seats->value)
                ->label(PlanConditionType::Seats->getLabel())
                ->icon(Heroicon::OutlinedUsers)
                ->maxItems(1)
                ->schema([
                    TextInput::make('count')
                        ->label(__('How many people, the buyer included'))
                        ->helperText(__("The buyer adds the others by their Gmail; each signs in with their own account. When the buyer's plan ends, everyone's does."))
                        ->numeric()
                        ->integer()
                        ->minValue(2)
                        ->maxValue(100)
                        ->required(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SubscriptionPlan $record): ?string => match (true) {
                        $record->is_default_free => __('Free plan'),
                        $record->is_trial => __('Trial'),
                        default => null,
                    }),
                TextColumn::make('target_role')->badge(),
                TextColumn::make('price')->money('BDT')->sortable(),
                TextColumn::make('duration')
                    ->label(__('Lasts'))
                    ->state(fn (SubscriptionPlan $record): string => $record->durationLabel()),
                TextColumn::make('conditions')
                    ->label(__('Conditions'))
                    ->state(fn (SubscriptionPlan $record): array => $record->planConditions()->descriptions())
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->wrap(),
                IconColumn::make('is_active')->boolean()->label(__('On sale')),
            ])
            // Every plan on one page, each row marked with its role, so the
            // All / Student / Teacher tabs can filter in the browser.
            ->paginated(false)
            ->recordClasses(fn (SubscriptionPlan $record): string => 'qb-plan-role-'.$record->target_role->value)
            ->recordActions(TableActions::editAndDelete(iconButtons: true))
            ->toolbarActions(TableActions::bulkDelete());
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubscriptionPlans::route('/'),
        ];
    }

    /**
     * A select whose options are an enum hands back the enum case itself.
     */
    private static function durationFrom(mixed $state): ?PlanDuration
    {
        return $state instanceof PlanDuration ? $state : PlanDuration::tryFrom((string) $state);
    }
}

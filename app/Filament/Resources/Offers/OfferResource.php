<?php

namespace App\Filament\Resources\Offers;

use App\Enums\UserRole;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Filament\Support\TableActions;
use App\Models\AcademicClass;
use App\Models\Offer;
use App\Services\OfferNotifier;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Offers and news (CLAUDE.md rule 13): an Admin writes one for some roles —
 * a student's offer for some classes, if wanted — with the dates it is on.
 * It shows on the home page and in those users' dashboard banner by
 * itself; "Send" also puts it in their bell and on their phones, once.
 */
class OfferResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Offer::class;

    protected static ?string $modelLabel = 'offer';

    protected static ?string $navigationLabel = 'Offers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Billing;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Offer'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->label(__('Title'))->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('body')->label(__('Text'))->required()->rows(4)->maxLength(2000)->columnSpanFull(),
                    FileUpload::make('image')
                        ->label(__('Image (optional)'))
                        ->image()
                        ->disk('public')->visibility('public')
                        ->directory('offers')
                        ->columnSpanFull(),
                    TextInput::make('button_label')->label(__('Button text (optional)'))->maxLength(60),
                    TextInput::make('button_url')
                        ->label(__('Button link (optional)'))
                        ->helperText(__('Where the button and the notification lead, e.g. /student/my-subscription.'))
                        ->maxLength(500)
                        ->rule('regex:/^(https?:\/\/|\/)/'),
                ]),

            Section::make(__('Who and when'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('target_roles')
                        ->label(__('For'))
                        ->options(collect(Offer::AUDIENCE_ROLES)->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])->all())
                        ->required()
                        ->columns(4)
                        ->live()
                        ->columnSpanFull(),
                    Select::make('academic_class_ids')
                        ->label(__('Only students of these classes'))
                        ->helperText(__('Leave it empty for every class.'))
                        ->multiple()
                        ->options(fn (): array => AcademicClass::options())
                        ->visible(fn (Get $get): bool => in_array(UserRole::Student->value, $get('target_roles') ?? [], true))
                        ->columnSpanFull(),
                    DateTimePicker::make('starts_at')->label(__('Starts'))->helperText(__('Empty: at once.')),
                    DateTimePicker::make('ends_at')->label(__('Ends'))->helperText(__('Empty: until switched off.'))->after('starts_at'),
                    Toggle::make('is_active')->label(__('On'))->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('Title'))->searchable()->wrap(),
                TextColumn::make('audience')
                    ->label(__('For'))
                    ->state(fn (Offer $record): array => $record->audienceLabels())
                    ->badge(),
                TextColumn::make('starts_at')->label(__('Starts'))->localDateTime()->placeholder(__('At once')),
                TextColumn::make('ends_at')->label(__('Ends'))->localDateTime()->placeholder('—'),
                IconColumn::make('live')
                    ->label(__('On now'))
                    ->state(fn (Offer $record): bool => $record->isLive())
                    ->boolean(),
                TextColumn::make('notified_at')->label(__('Sent'))->localDateTime()->placeholder(__('Not sent')),
            ])
            ->recordActions([
                self::sendAction(),
                ...TableActions::editAndDelete(iconButtons: true),
            ]);
    }

    /**
     * "Send": to the bell, as a push notification, or both — while the
     * offer is on, once (OfferNotifier).
     */
    private static function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('Send'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->visible(fn (Offer $record): bool => $record->canBeSent())
            ->modalHeading(fn (Offer $record): string => __('Send this offer: :title', ['title' => $record->title]))
            ->modalDescription(function (Offer $record): string {
                $reach = app(OfferNotifier::class)->reach($record);

                return __(':users users it is for; :push of them have push notifications on. It can be sent only once.', $reach);
            })
            ->schema([
                Toggle::make('to_bell')->label(__('To the bell'))->default(true),
                Toggle::make('to_push')->label(__('As a push notification'))->default(true),
            ])
            ->modalSubmitActionLabel(__('Send'))
            ->action(fn (Offer $record, array $data, ManageOffers $livewire, Action $action) => $livewire->send($record, $data, $action));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOffers::route('/'),
        ];
    }
}

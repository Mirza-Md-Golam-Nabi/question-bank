<?php

namespace App\Filament\Resources\Guides;

use App\Enums\GuideCategory;
use App\Enums\GuidePage;
use App\Enums\UserRole;
use App\Filament\Resources\Guides\Pages\ManageGuides;
use App\Filament\Support\Concerns\TranslatesResourceLabels;
use App\Filament\Support\NavigationGroup;
use App\Models\Guide;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The how-to guides (CLAUDE.md rule 14): an Admin writes each in Bangla
 * and, optionally, English, for some roles, files it under a category and
 * picks the pages whose "How do I…?" button opens it.
 */
class GuideResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Guide::class;

    protected static ?string $modelLabel = 'guide';

    protected static ?string $navigationLabel = 'Guides';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::Account;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->columnSpanFull()
                ->tabs([
                    Tab::make(__('Bangla'))->schema([
                        TextInput::make('title_bn')->label(__('Title'))->required()->maxLength(255),
                        self::bodyEditor('body_bn')->required(),
                    ]),
                    Tab::make(__('English'))->schema([
                        TextInput::make('title_en')->label(__('Title'))->maxLength(255)
                            ->helperText(__('Leave the English empty to show the Bangla to everyone.')),
                        self::bodyEditor('body_en'),
                    ]),
                ]),

            Section::make(__('Who and where'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('category')->label(__('Category'))->options(GuideCategory::class)->required(),
                    TextInput::make('video_url')
                        ->label(__('YouTube video (optional)'))
                        ->url()
                        ->maxLength(500)
                        ->rule('regex:/(youtu\.be|youtube\.com)/'),
                    CheckboxList::make('target_roles')
                        ->label(__('For'))
                        ->options(collect(Guide::AUDIENCE_ROLES)->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])->all())
                        ->required()
                        ->columns(4)
                        ->columnSpanFull(),
                    CheckboxList::make('page_keys')
                        ->label(__('Opens from the "How do I…?" button of'))
                        ->helperText(__('Shown only to the roles above, on the pages they have.'))
                        ->options(GuidePage::class)
                        ->columns(2)
                        ->columnSpanFull(),
                    TextInput::make('sort_order')->label(__('Order'))->integer()->default(0)->helperText(__('Smaller first, within its category.')),
                    Toggle::make('is_active')->label(__('On'))->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title_bn')->label(__('Title'))->searchable(['title_bn', 'title_en'])->wrap()
                    ->description(fn (Guide $record): ?string => $record->title_en),
                TextColumn::make('category')->label(__('Category'))->badge(),
                TextColumn::make('target_roles')
                    ->label(__('For'))
                    ->formatStateUsing(fn (string $state): string => (string) UserRole::tryFrom($state)?->getLabel())
                    ->badge(),
                TextColumn::make('page_keys')
                    ->label(__('Help button on'))
                    ->formatStateUsing(fn (string $state): string => (string) GuidePage::tryFrom($state)?->getLabel())
                    ->listWithLineBreaks()
                    ->placeholder('—'),
                IconColumn::make('video_url')->label(__('Video'))->boolean()->state(fn (Guide $record): bool => filled($record->video_url)),
                TextColumn::make('sort_order')->label(__('Order'))->sortable(),
                IconColumn::make('is_active')->label(__('On'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('Category'))->options(GuideCategory::class),
            ])
            ->recordActions([
                EditAction::make()->iconButton()->modalWidth(Width::FiveExtraLarge),
                DeleteAction::make()->iconButton(),
            ]);
    }

    /**
     * The steps, with pictures uploaded into the text.
     */
    private static function bodyEditor(string $field): RichEditor
    {
        return RichEditor::make($field)
            ->label(__('Steps'))
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsDirectory('guides')
            ->extraInputAttributes(['style' => 'min-height: 20rem']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGuides::route('/'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClanResource\Pages;
use App\Filament\Resources\ClanResource\RelationManagers;
use App\Models\Clan;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;

/**
 * Source: .planning/phases/02-clans-tags/02-12-PLAN.md task 1.
 *
 * ClanResource — admin CRUD for clans (D-012).
 * Form uses Tabs: Profile (all fields) + Audit tab.
 * RelationManagers (Members, Invites, Applications) are added via getRelations().
 *
 * discord_role_id + discord_announce_channel_id are protected behind an "Enable edit" toggle
 * to prevent accidental changes (T-02-09-02 acceptance).
 */
class ClanResource extends Resource
{
    protected static ?string $model = Clan::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.clan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.clan.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Tabs::make('clan_tabs')
                ->tabs([
                    Forms\Components\Tabs\Tab::make(__('admin.tab.profile'))
                        ->icon('heroicon-o-user-group')
                        ->schema([
                            Section::make(__('admin.clan.section.profile'))
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label(__('admin.clan.fields.name'))
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\TextInput::make('slug')
                                        ->label(__('admin.clan.fields.slug'))
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255),

                                    Forms\Components\TextInput::make('tag')
                                        ->label(__('admin.clan.fields.tag'))
                                        ->required()
                                        ->minLength(2)
                                        ->maxLength(8)
                                        ->regex('/^[a-zA-Z0-9_-]+$/'),

                                    // description is a JSONB locale-keyed column via HasTranslations.
                                    // KeyValue round-trips the array safely. Pitfall 6: ->default(['en' => ''])
                                    // prevents null submission on empty form.
                                    Forms\Components\KeyValue::make('description')
                                        ->label(__('admin.clan.fields.description'))
                                        ->keyLabel(__('admin.clan.fields.description_locale'))
                                        ->valueLabel(__('admin.clan.fields.description_text'))
                                        ->reorderable(false)
                                        ->default(['en' => ''])
                                        ->helperText(__('admin.clan.help.description_jsonb')),

                                    Forms\Components\TextInput::make('country_code')
                                        ->label(__('admin.clan.fields.country_code'))
                                        ->maxLength(2),

                                    Forms\Components\Select::make('owner_user_id')
                                        ->label(__('admin.clan.fields.owner'))
                                        ->relationship('owner', 'username')
                                        ->searchable()
                                        ->required(),

                                    Forms\Components\Select::make('status')
                                        ->label(__('admin.clan.fields.status'))
                                        ->options([
                                            'active' => 'Active',
                                            'suspended' => 'Suspended',
                                            'disbanded' => 'Disbanded',
                                        ])
                                        ->default('active')
                                        ->required(),

                                    Forms\Components\Select::make('tags')
                                        ->label(__('admin.clan.fields.tags'))
                                        ->multiple()
                                        ->relationship(titleAttribute: 'slug')
                                        ->preload(),

                                    Forms\Components\Toggle::make('accepts_applications')
                                        ->label(__('admin.clan.fields.accepts_applications'))
                                        ->helperText(__('admin.clan.fields.accepts_applications_help'))
                                        ->default(true),

                                    // Discord fields are protected by an "Enable edit" toggle (T-02-09-02 mitigation).
                                    // Admin must explicitly enable editing to prevent accidental snowflake changes.
                                    Forms\Components\Toggle::make('discord_advanced_fields_enabled')
                                        ->label('Enable Discord field editing')
                                        ->dehydrated(false)
                                        ->live()
                                        ->helperText('Toggle on to edit Discord role/channel IDs. These are Discord snowflakes — changing them breaks bot sync.'),

                                    Forms\Components\TextInput::make('discord_role_id')
                                        ->label(__('admin.clan.fields.discord_role_id'))
                                        ->disabled(fn (Forms\Get $get): bool => ! $get('discord_advanced_fields_enabled'))
                                        ->dehydrated(fn (Forms\Get $get): bool => $get('discord_advanced_fields_enabled') === true),

                                    Forms\Components\TextInput::make('discord_announce_channel_id')
                                        ->label(__('admin.clan.fields.discord_announce_channel_id'))
                                        ->helperText(__('admin.clan.fields.discord_announce_channel_id_help'))
                                        ->maxLength(20)
                                        ->disabled(fn (Forms\Get $get): bool => ! $get('discord_advanced_fields_enabled'))
                                        ->dehydrated(fn (Forms\Get $get): bool => $get('discord_advanced_fields_enabled') === true),
                                ]),
                        ]),

                    Forms\Components\Tabs\Tab::make(__('admin.tab.audit'))
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            Forms\Components\Placeholder::make('audit_log')
                                ->label('')
                                ->content(fn ($record): View|string => $record !== null
                                    ? view('filament.partials.audit-tab', ['subject' => $record])
                                    : (string) __('admin.audit.no_activity_yet')),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.clan.fields.slug'))
                    ->searchable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.clan.fields.name'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('tag')
                    ->label(__('admin.clan.fields.tag'))
                    ->fontFamily('mono'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('admin.clan.fields.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'suspended',
                        'danger' => 'disbanded',
                    ]),

                Tables\Columns\TextColumn::make('owner.username')
                    ->label(__('admin.clan.fields.owner'))
                    ->url(fn ($record) => $record->owner
                        ? route('filament.admin.resources.users.edit', $record->owner)
                        : null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.player.fields.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'disbanded' => 'Disbanded',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn ($record) => auth()->user()?->can('forceDelete', $record) ?? false),
            ]);
    }

    /** @return array<class-string> */
    public static function getRelations(): array
    {
        return [
            RelationManagers\MembersRelationManager::class,
            RelationManagers\InvitesRelationManager::class,
            RelationManagers\ApplicationsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClans::route('/'),
            'create' => Pages\CreateClan::route('/create'),
            'view' => Pages\ViewClan::route('/{record}'),
            'edit' => Pages\EditClan::route('/{record}/edit'),
        ];
    }
}

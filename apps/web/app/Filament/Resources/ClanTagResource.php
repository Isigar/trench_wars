<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClanTagResource\Pages;
use App\Models\ClanTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Source: .planning/phases/02-clans-tags/02-12-PLAN.md task 2.
 *
 * ClanTagResource — admin CRUD for clan tags (D-012).
 * No Delete action at the table level (UI-SPEC: "tags may be referenced").
 *
 * Auto-slug-from-label: KeyValue live update triggers slug generation from label['en'].
 */
class ClanTagResource extends Resource
{
    protected static ?string $model = ClanTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('admin.clan_tag.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.clan_tag.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('slug')
                ->label(__('admin.clan_tag.fields.slug'))
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255),

            // label is a JSONB locale-keyed column via HasTranslations.
            // Auto-updates slug on blur from label['en'] value.
            Forms\Components\KeyValue::make('label')
                ->label(__('admin.clan_tag.fields.label'))
                ->keyLabel(__('admin.clan_tag.fields.label_locale'))
                ->valueLabel(__('admin.clan_tag.fields.label_text'))
                ->reorderable(false)
                ->default(['en' => ''])
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, Forms\Set $set): void {
                    if (is_array($state) && isset($state['en']) && $state['en'] !== '') {
                        $set('slug', Str::slug($state['en']));
                    }
                }),

            Forms\Components\ColorPicker::make('color')
                ->label(__('admin.clan_tag.fields.color')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('admin.clan_tag.fields.slug'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('label')
                    ->label(__('admin.clan_tag.fields.label'))
                    ->getStateUsing(fn ($record): string => is_array($record->label) ? ($record->label['en'] ?? '—') : '—'),

                Tables\Columns\ColorColumn::make('color')
                    ->label(__('admin.clan_tag.fields.color')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — tags may be referenced by clans (UI-SPEC).
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClanTags::route('/'),
            'create' => Pages\CreateClanTag::route('/create'),
            // No 'view' route — tight resource; edit covers inspection needs.
            'edit' => Pages\EditClanTag::route('/{record}/edit'),
        ];
    }
}

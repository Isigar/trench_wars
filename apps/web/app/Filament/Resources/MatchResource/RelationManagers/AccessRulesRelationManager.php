<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 2.
 *
 * MatchAccessRule CRUD on the MatchResource edit page (Pattern 5 — tag-based signup gate).
 * Empty list = match is open to all clans; >=1 rule = only clans carrying one of the
 * listed tags may sign up (consumed by MatchSignupService — plan 04-06).
 *
 * Composite UNIQUE (match_id, clan_tag_id) blocks duplicates at the DB layer
 * (plan 04-02 migration); the Select preloads existing ClanTags so admin can pick.
 *
 * Pitfall 3 mitigation: $relationship MUST match GameMatch::accessRules() HasMany.
 */
class AccessRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'accessRules';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_access_rule.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('clan_tag_id')
                ->label(__('admin.match_access_rule.fields.clan_tag'))
                ->relationship('clanTag', 'slug')
                ->required()
                ->searchable()
                ->preload(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clanTag.slug')
                    ->label(__('admin.match_access_rule.fields.clan_tag'))
                    ->fontFamily('mono')
                    ->searchable(),

                Tables\Columns\TextColumn::make('clanTag.label')
                    ->label('Label')
                    ->getStateUsing(function ($record): string {
                        $tag = $record->clanTag;
                        if ($tag === null) {
                            return '—';
                        }
                        $raw = $tag->getAttributes()['label'] ?? null;
                        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                        return is_array($decoded) ? ($decoded['en'] ?? $tag->slug) : $tag->slug;
                    }),
            ])
            // Pattern 5 UX: empty list = match open to all clans.
            ->emptyStateHeading(__('admin.match_access_rule.empty_heading'))
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

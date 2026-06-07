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
 * Per-player match-stat correction surface on the Match edit page.
 *
 * MatchPlayerStat documents an "admin manually corrects a stat" flow (D-012,
 * LogsActivity covers create/update for non-repudiation) but had NO Filament
 * surface — MatchResource exposed Slots/AccessRules/Result/Mvps/Bookings, not
 * stats. So when a CRCON-aggregated kill/death/score count was wrong, an admin
 * had no UI to fix it. This relation manager closes that: editing a row routes
 * through Eloquent update → the MatchPlayerStatObserver audit fires.
 *
 * No create/delete: stat rows are produced by the aggregator (one per
 * match+player, composite-unique). The admin corrects existing rows only.
 *
 * Pitfall 3: $relationship MUST match GameMatch::playerStats() HasMany method.
 */
class PlayerStatsRelationManager extends RelationManager
{
    protected static string $relationship = 'playerStats';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_player_stats.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('kills')
                ->label(__('admin.match_player_stats.fields.kills'))
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\TextInput::make('deaths')
                ->label(__('admin.match_player_stats.fields.deaths'))
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\TextInput::make('team_kills')
                ->label(__('admin.match_player_stats.fields.team_kills'))
                ->numeric()
                ->minValue(0)
                ->required(),
            Forms\Components\TextInput::make('score')
                ->label(__('admin.match_player_stats.fields.score'))
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('role_played')
                ->label(__('admin.match_player_stats.fields.role_played'))
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('player.display_name')
                    ->label(__('admin.match_player_stats.fields.player_id'))
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kills')
                    ->label(__('admin.match_player_stats.fields.kills'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('deaths')
                    ->label(__('admin.match_player_stats.fields.deaths'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('team_kills')
                    ->label(__('admin.match_player_stats.fields.team_kills'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->label(__('admin.match_player_stats.fields.score'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('role_played')
                    ->label(__('admin.match_player_stats.fields.role_played'))
                    ->placeholder('—'),
            ])
            ->defaultSort('score', 'desc')
            // No create — rows come from the aggregator (composite-unique per
            // match+player). Admin corrects existing rows only.
            ->headerActions([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}

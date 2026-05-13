<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameMatchTypeResource\RelationManagers;

use App\Models\GameMatchType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/03-games-match-types/03-07-PLAN.md task 2.
 *
 * Inline GameMatchTypeRoleLimit CRUD on the GameMatchType edit page (RESEARCH.md
 * Pattern 1 second-tier; Pattern 2 click-through lands admin here from /admin/games).
 *
 * Pattern 3 — cross-game scoped Select (defense-in-depth UI half of Pitfall 10):
 * the `game_role_id` Select options lambda uses `getOwnerRecord()` to resolve the
 * parent GameMatchType, then traverses ->game->roles() to return ONLY roles of the
 * same parent game. Admin cannot pick a role from a different game via the UI.
 *
 * The model-layer half (Pitfall 10 defense-in-depth):
 *   App\Models\GameMatchTypeRoleLimit::booted() registers a saving() listener that
 *   throws DomainException when matchType.game_id !== role.game_id. That catches
 *   API/Console writes that bypass the Filament Select.
 *
 * Pitfall 3 mitigation: $relationship MUST match GameMatchType::roleLimits() HasMany.
 */
class RoleLimitsRelationManager extends RelationManager
{
    protected static string $relationship = 'roleLimits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.game_match_type_role_limit.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // Pattern 3 verbatim (RESEARCH.md): getOwnerRecord() resolves the parent
            // GameMatchType, then ->game->roles() yields only roles of the same game.
            //
            // Note: ->game is BelongsTo and may be null statically; in practice the Edit
            // page can only be reached for a persisted MatchType with a parent Game (NOT
            // NULL FK at the DB layer), so the assertion is safe. The ?->game guard
            // satisfies PHPStan L8 and returns an empty list if the relation is missing.
            //
            // The role->display_name access reads the raw JSONB attribute via
            // getAttributes() to avoid the HasTranslations accessor that returns the
            // translated string (PHPStan would otherwise see string and trip is_array).
            Forms\Components\Select::make('game_role_id')
                ->label(__('admin.game_match_type_role_limit.fields.role'))
                ->options(function (RelationManager $livewire): array {
                    /** @var GameMatchType $matchType */
                    $matchType = $livewire->getOwnerRecord();
                    $game = $matchType->game;

                    if ($game === null) {
                        return [];
                    }

                    return $game->roles()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(function ($role): array {
                            $raw = $role->getAttributes()['display_name'] ?? null;
                            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
                            $label = is_array($decoded)
                                ? ($decoded['en'] ?? $role->key)
                                : $role->key;

                            return [$role->id => $label];
                        })
                        ->toArray();
                })
                ->required()
                ->searchable()
                ->helperText(__('admin.game_match_type_role_limit.help.role_scope')),

            // V5 defense-in-depth — Filament form-layer minValue(0); DB CHECK (plan 03-02) is second gate.
            Forms\Components\TextInput::make('capacity')
                ->label(__('admin.game_match_type_role_limit.fields.capacity'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->helperText(__('admin.game_match_type_role_limit.help.capacity_min_zero')),

            Forms\Components\TextInput::make('sort_order')
                ->label(__('admin.game_match_type_role_limit.fields.sort_order'))
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('admin.game_match_type_role_limit.fields.sort_order'))
                    ->sortable(),

                // Defensive JSONB extraction — read raw attribute to bypass the
                // HasTranslations string accessor (PHPStan L8 sees string on typed model).
                Tables\Columns\TextColumn::make('role.display_name')
                    ->label(__('admin.game_match_type_role_limit.fields.role'))
                    ->getStateUsing(function ($record): string {
                        $role = $record->role;
                        if ($role === null) {
                            return '—';
                        }
                        $raw = $role->getAttributes()['display_name'] ?? null;
                        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                        return is_array($decoded) ? ($decoded['en'] ?? '—') : '—';
                    }),

                Tables\Columns\TextColumn::make('capacity')
                    ->label(__('admin.game_match_type_role_limit.fields.capacity'))
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

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
 * Slots are owned by `MatchSlotMaterialiserService` (plan 04-05) and `MatchSignupService`
 * (plan 04-06) — admin override of `occupant_user_id` should be rare. Most fields are
 * displayed read-only; only `occupant_user_id` and `confirmed_at` are editable.
 *
 * Pitfall 3 mitigation: $relationship MUST match GameMatch::slots() HasMany method name
 * EXACTLY — a typo silently renders an empty tab.
 *
 * No headerActions (CreateAction) — slot count is invariant (the materialiser owns it).
 * No DeleteAction — same rationale.
 */
class SlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'slots';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_slot.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // game_role is snapshot at materialise-time (plan 04-05) — read-only.
            Forms\Components\Select::make('game_role_id')
                ->label(__('admin.match_slot.fields.game_role'))
                ->relationship('role', 'key')
                ->disabled(),

            Forms\Components\TextInput::make('slot_index')
                ->label(__('admin.match_slot.fields.slot_index'))
                ->numeric()
                ->disabled(),

            // The ONE field admins legitimately override (D-010 acknowledges admin
            // override as the escape hatch; the partial UNIQUE constraint per match
            // still catches duplicate occupants at the DB layer).
            Forms\Components\Select::make('occupant_user_id')
                ->label(__('admin.match_slot.fields.occupant_user'))
                ->relationship('occupantUser', 'username')
                ->searchable()
                ->nullable(),

            Forms\Components\DateTimePicker::make('confirmed_at')
                ->label(__('admin.match_slot.fields.confirmed_at'))
                ->seconds(false)
                ->nullable(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('slot_index')
                    ->label(__('admin.match_slot.fields.slot_index'))
                    ->numeric(),

                // Defensive JSONB extraction — read raw attribute to bypass the
                // HasTranslations string accessor (PHPStan L8 sees string on typed model).
                Tables\Columns\TextColumn::make('role.display_name')
                    ->label(__('admin.match_slot.fields.game_role'))
                    ->getStateUsing(function ($record): string {
                        $role = $record->role;
                        if ($role === null) {
                            return '—';
                        }
                        $raw = $role->getAttributes()['display_name'] ?? null;
                        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

                        return is_array($decoded) ? ($decoded['en'] ?? $role->key) : $role->key;
                    }),

                Tables\Columns\TextColumn::make('occupantUser.username')
                    ->label(__('admin.match_slot.fields.occupant_user'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('confirmed_at')
                    ->label(__('admin.match_slot.fields.confirmed_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                // INTENTIONALLY no DeleteAction — slot count is invariant.
            ]);
    }
}

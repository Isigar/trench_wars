<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\RelationManagers;

use App\Models\GameMatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 2 + Pitfall 11 mitigation.
 *
 * MatchMvp lives on the GRAND-CHILD side of the FK chain:
 *
 *     GameMatch.id  ◄────── MatchResult.match_id
 *                              MatchResult.id  ◄────── MatchMvp.match_result_id
 *
 * Filament v3 RelationManagers natively support HasManyThrough relations (Context7
 * filamentphp_3_x §relation-managers "Compatible with HasMany, HasManyThrough,
 * BelongsToMany, MorphMany and MorphToMany relationships."), so the cleanest
 * resolution to Pitfall 11 is to add a `mvps()` HasManyThrough on the GameMatch
 * model (done in plan 04-09 task 2 Rule-2 amendment to App\Models\GameMatch) and
 * use `$relationship = 'mvps';` here.
 *
 * The Filament CreateAction is hidden when the parent match has no MatchResult yet —
 * MatchMvp has a NOT NULL FK on match_result_id, so the UI gate is mirrored by the
 * DB CHECK. Admin can only create MVPs after the result row has been written via
 * the ResultRelationManager (Pattern 4 + SC-4).
 *
 * Pitfall 3 mitigation: $relationship matches GameMatch::mvps() HasManyThrough.
 */
class MvpsRelationManager extends RelationManager
{
    protected static string $relationship = 'mvps';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_mvp.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            // player_id is the MatchMvp FK to Player; Select preloads on slug for clarity.
            Forms\Components\Select::make('player_id')
                ->label(__('admin.match_mvp.fields.player'))
                ->relationship('player', 'slug')
                ->required()
                ->searchable(),

            Forms\Components\Select::make('category')
                ->label(__('admin.match_mvp.fields.category'))
                ->options([
                    'kills' => 'Kills',
                    'defense' => 'Defense',
                    'objective' => 'Objective',
                    'mvp' => 'MVP',
                ])
                ->required(),

            Forms\Components\TextInput::make('value')
                ->label(__('admin.match_mvp.fields.value'))
                ->numeric()
                ->nullable(),

            // match_result_id is NOT in the form — the CreateAction `->using()` handler
            // below fills it from the owner match's result row (one hop through the
            // HasManyThrough chain).
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('player.slug')
                    ->label(__('admin.match_mvp.fields.player'))
                    ->fontFamily('mono'),

                Tables\Columns\BadgeColumn::make('category')
                    ->label(__('admin.match_mvp.fields.category'))
                    ->colors([
                        'success' => 'kills',
                        'info' => 'defense',
                        'warning' => 'objective',
                        'primary' => 'mvp',
                    ]),

                Tables\Columns\TextColumn::make('value')
                    ->label(__('admin.match_mvp.fields.value'))
                    ->numeric()
                    ->placeholder('—'),
            ])
            ->headerActions([
                // CreateAction is only enabled when a MatchResult exists — MatchMvp.match_result_id
                // is NOT NULL at the DB layer; without the parent result row the insert would throw
                // a FK violation.
                Tables\Actions\CreateAction::make()
                    ->visible(function (): bool {
                        /** @var GameMatch $match */
                        $match = $this->getOwnerRecord();

                        return $match->result !== null;
                    })
                    ->using(function (array $data) {
                        /** @var GameMatch $match */
                        $match = $this->getOwnerRecord();
                        $result = $match->result;
                        // visible() above guards against this; defensive null-check for PHPStan.
                        if ($result === null) {
                            throw new \DomainException('Cannot create MatchMvp without a MatchResult.');
                        }

                        return $result->mvps()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

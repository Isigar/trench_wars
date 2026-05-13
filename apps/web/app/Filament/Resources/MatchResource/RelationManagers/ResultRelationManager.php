<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\RelationManagers;

use App\Models\GameMatch;
use App\Models\MatchResult;
use App\Models\User;
use App\Services\MatchResultService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 2.
 *
 * HasOne (1:1) RelationManager on GameMatch::result. Filament v3 supports HasOne
 * RelationManagers — the table renders a single row (or empty state) and the
 * Create/Edit actions form route through MatchResultService::upsert (Pattern 4 +
 * SC-4) which atomically writes the result + flips status to 'played' via
 * MatchStatusService::transition (T-04-09-04 mitigation).
 *
 * Pitfall 3 mitigation: $relationship MUST match GameMatch::result() HasOne method
 * name EXACTLY.
 */
class ResultRelationManager extends RelationManager
{
    protected static string $relationship = 'result';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.match_result.plural_label');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('winner_clan_id')
                ->label(__('admin.match_result.fields.winner_clan'))
                ->relationship('winnerClan', 'slug')
                ->searchable()
                ->nullable(),

            Forms\Components\TextInput::make('allies_score')
                ->label(__('admin.match_result.fields.allies_score'))
                ->numeric()
                ->minValue(0)
                ->nullable(),

            Forms\Components\TextInput::make('axis_score')
                ->label(__('admin.match_result.fields.axis_score'))
                ->numeric()
                ->minValue(0)
                ->nullable(),

            Forms\Components\Textarea::make('notes')
                ->label(__('admin.match_result.fields.notes'))
                ->nullable(),

            Forms\Components\DateTimePicker::make('recorded_at')
                ->label(__('admin.match_result.fields.recorded_at'))
                ->seconds(false)
                ->default(now()),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('winnerClan.slug')
                    ->label(__('admin.match_result.fields.winner_clan'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('allies_score')
                    ->label(__('admin.match_result.fields.allies_score'))
                    ->numeric()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('axis_score')
                    ->label(__('admin.match_result.fields.axis_score'))
                    ->numeric()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('recorded_at')
                    ->label(__('admin.match_result.fields.recorded_at'))
                    ->dateTime()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('recordedBy.username')
                    ->label(__('admin.match_result.fields.recorded_by'))
                    ->placeholder('—'),
            ])
            ->headerActions([
                // CreateAction routes through MatchResultService::upsert so the result
                // write + status flip to 'played' happen atomically inside one
                // DB::transaction (T-04-09-04 mitigation).
                Tables\Actions\CreateAction::make()
                    ->visible(function (): bool {
                        /** @var GameMatch $match */
                        $match = $this->getOwnerRecord();

                        return $match->result === null;
                    })
                    ->using(function (array $data): MatchResult {
                        /** @var GameMatch $match */
                        $match = $this->getOwnerRecord();
                        /** @var User $causer */
                        $causer = auth()->user();

                        return app(MatchResultService::class)->upsert($match, $data, $causer);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->using(function (Model $record, array $data): MatchResult {
                        /** @var GameMatch $match */
                        $match = $this->getOwnerRecord();
                        /** @var User $causer */
                        $causer = auth()->user();

                        return app(MatchResultService::class)->upsert($match, $data, $causer);
                    }),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}

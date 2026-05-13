<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchResource\Pages;

use App\Filament\Resources\MatchResource;
use App\Models\GameMatch;
use App\Services\MatchSlotMaterialiserService;
use Filament\Forms;
use Filament\Forms\Components\Wizard\Step;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Source: .planning/phases/04-matches-manual/04-09-PLAN.md task 1 + RESEARCH Pattern 6.
 *
 * 3-step HasWizard create flow:
 *   Step 1 — Match type:   game_match_type_id (Select) — drives slot materialisation.
 *   Step 2 — Schedule:     scheduled_at, organiser_user_id, host_clan_id, server_address,
 *                          is_public.
 *   Step 3 — Review:       title (KeyValue, required), description (KeyValue, nullable).
 *
 * Pitfall 3 (RESEARCH.md): handleRecordCreation explicitly wraps GameMatch::create AND
 * MatchSlotMaterialiserService::materialise in a SINGLE DB::transaction. Filament v3
 * does NOT auto-wrap handleRecordCreation, so without this wrap a materialiser throw
 * leaves an orphan GameMatch row with zero slots (T-04-09-02).
 *
 * Pitfall 2 (RESEARCH.md): translatable JSONB fields (title, description) need
 * null-coercion to ['en' => ''] in mutateFormDataBeforeCreate — Filament KeyValue
 * returns null on empty submission; HasTranslations expects an array.
 */
class CreateMatch extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = MatchResource::class;

    /** @return array<int, Step> */
    protected function getSteps(): array
    {
        return [
            Step::make(__('admin.match.wizard.step_type'))
                ->description(__('admin.match.wizard.step_type_desc'))
                ->schema([
                    Forms\Components\Select::make('game_match_type_id')
                        ->label(__('admin.match.fields.game_match_type'))
                        ->relationship('gameMatchType', 'key')
                        ->required()
                        ->searchable()
                        ->preload(),
                ]),

            Step::make(__('admin.match.wizard.step_schedule'))
                ->description(__('admin.match.wizard.step_schedule_desc'))
                ->schema([
                    Forms\Components\DateTimePicker::make('scheduled_at')
                        ->label(__('admin.match.fields.scheduled_at'))
                        ->seconds(false)
                        ->timezone('UTC')
                        ->required(),

                    Forms\Components\Select::make('organiser_user_id')
                        ->label(__('admin.match.fields.organiser'))
                        ->relationship('organiser', 'username')
                        ->required()
                        ->searchable(),

                    Forms\Components\Select::make('host_clan_id')
                        ->label(__('admin.match.fields.host_clan'))
                        ->relationship('hostClan', 'slug')
                        ->searchable()
                        ->nullable(),

                    Forms\Components\TextInput::make('server_address')
                        ->label(__('admin.match.fields.server_address'))
                        ->maxLength(255)
                        ->nullable(),

                    Forms\Components\Toggle::make('is_public')
                        ->label(__('admin.match.fields.is_public'))
                        ->default(true),
                ]),

            Step::make(__('admin.match.wizard.step_review'))
                ->description(__('admin.match.wizard.step_review_desc'))
                ->schema([
                    Forms\Components\KeyValue::make('title')
                        ->label(__('admin.match.fields.title'))
                        ->reorderable(false)
                        ->default(['en' => ''])
                        ->required(),

                    Forms\Components\KeyValue::make('description')
                        ->label(__('admin.match.fields.description'))
                        ->reorderable(false)
                        ->default(['en' => '']),
                ]),
        ];
    }

    /**
     * Coerce null translatable JSONB fields to ['en' => ''] before DB write.
     *
     * Pitfall 2 (RESEARCH.md): Filament KeyValue returns null on empty submission;
     * HasTranslations expects an array, not null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['title'] = $data['title'] ?: ['en' => ''];
        $data['description'] = $data['description'] ?: ['en' => ''];

        return $data;
    }

    /**
     * Pitfall 3 verbatim: SINGLE DB::transaction wraps GameMatch::create AND
     * MatchSlotMaterialiserService::materialise so a materialiser failure
     * rolls back the parent Match row — no orphan rows ever land.
     *
     * SC-1 (admin creates match → signups open immediately): status defaults to
     * 'open' here so freshly-created matches accept signups without an admin
     * having to flip status manually.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): GameMatch {
            $data['status'] = $data['status'] ?? 'open';

            /** @var GameMatch $match */
            $match = static::getModel()::create($data);

            app(MatchSlotMaterialiserService::class)->materialise($match);

            return $match;
        });
    }
}

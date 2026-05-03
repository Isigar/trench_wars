<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\PlayerPrivacyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source: .docs/05-database-schema.md § player_privacy + D-018.
 *
 * Per-section booleans + global tier (`show_to`). Defaults per D-018:
 * - show_real_name → false (sensitive PII)
 * - all other show_* → true
 * - show_to → 'community' (visible to logged-in league members)
 *
 * Table name overridden because Eloquent's default would pluralize to "player_privacies".
 */
class PlayerPrivacy extends Model
{
    /** @use HasFactory<PlayerPrivacyFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;

    protected $table = 'player_privacy';

    /** @var list<string> */
    protected $fillable = [
        'player_id',
        'show_to',
        'show_real_name',
        'show_discord_tag',
        'show_clan_history',
        'show_match_history',
        'show_stats',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'show_real_name' => 'boolean',
            'show_discord_tag' => 'boolean',
            'show_clan_history' => 'boolean',
            'show_match_history' => 'boolean',
            'show_stats' => 'boolean',
        ];
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}

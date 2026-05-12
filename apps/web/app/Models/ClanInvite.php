<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanInviteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .docs/05-database-schema.md § clan_invites.
 *
 * State machine: pending → accepted | declined | revoked | expired.
 * State transitions go through ClanInviteService (plan 02-09+).
 */
class ClanInvite extends Model
{
    /** @use HasFactory<ClanInviteFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'clan_id',
        'invited_user_id',
        'inviting_user_id',
        'status',
        'message',
        'decided_at',
        'expires_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "ClanInvite {$event}");
    }

    /** @return BelongsTo<Clan, $this> */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviting_user_id');
    }
}

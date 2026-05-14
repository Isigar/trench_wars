<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\MatchServerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Source: .planning/phases/08-rcon-automation/08-03-PLAN.md task 1 +
 *         08-02 migration `2026_05_16_100000_create_match_servers_table.php`.
 *
 * League-owned CRCON server registry (D-005, REQ-constraint-league-owns-servers).
 *
 * `credentials_encrypted` uses Laravel's `encrypted:array` cast — values at rest
 * are envelope-encrypted under APP_KEY (T-08-03-01 mitigation). The cast handles
 * both directions: arrays in, ciphertext-on-the-column, arrays-back-out. NEVER
 * write to this column directly via DB::table() — always use the Eloquent path.
 *
 * LogsActivity (D-012 + CLAUDE.md §6): every change (especially API token
 * rotation + host changes) appended to activity_log for non-repudiation
 * (T-08-03-03 mitigation).
 */
class MatchServer extends Model
{
    /** @use HasFactory<MatchServerFactory> */
    use HasFactory;

    use HasUuidPrimaryKey;
    use LogsActivity;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'host',
        'port_rcon',
        'region',
        'credentials_encrypted',
        'is_active',
        'last_test_at',
        'last_test_status',
        'last_test_error',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_test_at' => 'datetime',
            'port_rcon' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $event): string => "MatchServer {$event}");
    }

    /** @return HasMany<MatchServerBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(MatchServerBooking::class, 'server_id');
    }

    /**
     * Active servers only — Filament server-picker dropdown predicate.
     *
     * @param  Builder<MatchServer>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}

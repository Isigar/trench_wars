# Phase 2: Clans & tags - Pattern Map

**Mapped:** 2026-05-12
**Files analyzed:** 68 new/modified files
**Analogs found:** 61 / 68 (7 have no close analog — noted in final section)

---

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `database/migrations/…create_discord_guild_table.php` | migration | CRUD | `2026_05_03_100000_create_users_table.php` | exact |
| `database/migrations/…create_clans_table.php` | migration | CRUD | `2026_05_03_100100_create_players_table.php` | exact |
| `database/migrations/…create_clan_tags_table.php` | migration | CRUD | `2026_05_03_100100_create_players_table.php` | role-match |
| `database/migrations/…create_clan_clan_tag_table.php` | migration | CRUD | `2026_05_03_100200_create_player_privacy_table.php` | role-match |
| `database/migrations/…create_clan_memberships_table.php` | migration | CRUD | `2026_05_03_100200_create_player_privacy_table.php` + raw-SQL pattern | exact |
| `database/migrations/…create_clan_invites_table.php` | migration | CRUD | `2026_05_03_100200_create_player_privacy_table.php` | role-match |
| `database/migrations/…create_clan_applications_table.php` | migration | CRUD | `2026_05_03_100200_create_player_privacy_table.php` | role-match |
| `app/Models/Clan.php` | model | CRUD | `app/Models/Player.php` | exact |
| `app/Models/ClanTag.php` | model | CRUD | `app/Models/PlayerPrivacy.php` | role-match |
| `app/Models/ClanMembership.php` | model | CRUD | `app/Models/PlayerPrivacy.php` | role-match |
| `app/Models/ClanInvite.php` | model | CRUD | `app/Models/PlayerPrivacy.php` | role-match |
| `app/Models/ClanApplication.php` | model | CRUD | `app/Models/PlayerPrivacy.php` | role-match |
| `app/Models/DiscordGuild.php` | model | CRUD | `app/Models/PlayerPrivacy.php` | role-match |
| `database/factories/ClanFactory.php` | factory | CRUD | `database/factories/PlayerFactory.php` | exact |
| `database/factories/ClanTagFactory.php` | factory | CRUD | `database/factories/UserFactory.php` | role-match |
| `database/factories/ClanMembershipFactory.php` | factory | CRUD | `database/factories/PlayerPrivacyFactory.php` | exact |
| `database/factories/ClanInviteFactory.php` | factory | CRUD | `database/factories/PlayerPrivacyFactory.php` | role-match |
| `database/factories/ClanApplicationFactory.php` | factory | CRUD | `database/factories/PlayerPrivacyFactory.php` | role-match |
| `database/factories/DiscordGuildFactory.php` | factory | CRUD | `database/factories/UserFactory.php` | role-match |
| `database/seeders/DiscordGuildSeeder.php` | seeder | CRUD | no exact analog | partial |
| `database/seeders/ClanTagSeeder.php` | seeder | CRUD | no exact analog | partial |
| `app/Data/ClanData.php` | DTO | request-response | `app/Data/PlayerData.php` | exact |
| `app/Data/ClanTagData.php` | DTO | request-response | `app/Data/PlayerPrivacyData.php` | exact |
| `app/Data/ClanMembershipData.php` | DTO | request-response | `app/Data/PlayerPrivacyData.php` | role-match |
| `app/Data/ClanInviteData.php` | DTO | request-response | `app/Data/PlayerPrivacyData.php` | role-match |
| `app/Data/ClanApplicationData.php` | DTO | request-response | `app/Data/PlayerPrivacyData.php` | role-match |
| `app/Data/PlayerProfileData.php` | DTO | request-response | `app/Data/PlayerData.php` | role-match |
| `app/Http/Controllers/ClanDirectoryController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` (single-action) | role-match |
| `app/Http/Controllers/ClanShowController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/PlayerProfileController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/MyClan/MyClanController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/MyClan/MyClanProfileController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/MyClan/MyClanMemberController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/MyClan/ClanInviteController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/MyClan/ClanApplicationController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Http/Controllers/Clans/ClanCreateController.php` | controller | request-response | `app/Http/Controllers/Auth/LogoutController.php` | role-match |
| `app/Filament/Resources/ClanResource.php` | Filament resource | request-response | `app/Filament/Resources/PlayerResource.php` | exact |
| `app/Filament/Resources/ClanResource/Pages/ListClans.php` | Filament page | request-response | `app/Filament/Resources/UserResource/Pages/ListUsers.php` | exact |
| `app/Filament/Resources/ClanResource/Pages/CreateClan.php` | Filament page | request-response | `app/Filament/Resources/RoleResource/Pages/CreateRole.php` | exact |
| `app/Filament/Resources/ClanResource/Pages/EditClan.php` | Filament page | request-response | `app/Filament/Resources/PlayerResource/Pages/EditPlayer.php` | exact |
| `app/Filament/Resources/ClanResource/Pages/ViewClan.php` | Filament page | request-response | `app/Filament/Resources/PlayerResource/Pages/ViewPlayer.php` | exact |
| `app/Filament/Resources/ClanResource/RelationManagers/MembersRelationManager.php` | Filament relation manager | CRUD | no existing RelationManager in codebase | no analog |
| `app/Filament/Resources/ClanTagResource.php` | Filament resource | request-response | `app/Filament/Resources/PermissionResource.php` (simple list+edit) | role-match |
| `app/Filament/Resources/ClanTagResource/Pages/ListClanTags.php` | Filament page | request-response | `app/Filament/Resources/UserResource/Pages/ListUsers.php` | exact |
| `app/Filament/Resources/ClanTagResource/Pages/CreateClanTag.php` | Filament page | request-response | `app/Filament/Resources/RoleResource/Pages/CreateRole.php` | exact |
| `app/Filament/Resources/ClanTagResource/Pages/EditClanTag.php` | Filament page | request-response | `app/Filament/Resources/PlayerResource/Pages/EditPlayer.php` | exact |
| `app/Filament/Resources/ClanMembershipResource.php` | Filament resource | request-response | `app/Filament/Resources/PermissionResource.php` | role-match |
| `app/Filament/Resources/ClanInviteResource.php` | Filament resource | request-response | `app/Filament/Resources/PermissionResource.php` | role-match |
| `app/Filament/Resources/ClanApplicationResource.php` | Filament resource | request-response | `app/Filament/Resources/PermissionResource.php` | role-match |
| `app/Filament/Resources/DiscordGuildResource.php` | Filament resource | request-response | `app/Filament/Resources/PermissionResource.php` (no-Create pattern) | exact |
| `app/Policies/ClanPolicy.php` | policy | request-response | no existing Policy in codebase | no analog |
| `app/Policies/ClanMembershipPolicy.php` | policy | request-response | no existing Policy in codebase | no analog |
| `app/Services/PlayerPrivacyGate.php` | service | request-response | no analog — new pattern | no analog |
| `app/Services/ClanSlugGenerator.php` | service | CRUD | no analog | no analog |
| `app/Services/ClanInviteService.php` | service | CRUD | no analog | no analog |
| `app/Services/ClanApplicationService.php` | service | CRUD | no analog | no analog |
| `resources/js/pages/Clans/Index.vue` | Vue page | request-response | `resources/js/pages/Home.vue` | role-match |
| `resources/js/pages/Clans/Show.vue` | Vue page | request-response | `resources/js/pages/Home.vue` | role-match |
| `resources/js/pages/Players/Show.vue` | Vue page | request-response | `resources/js/pages/Home.vue` | role-match |
| `resources/js/pages/MyClan/Index.vue` | Vue page | request-response | `resources/js/pages/Home.vue` | role-match |
| `resources/js/components/clans/ClanCard.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/clans/ClanTagBadge.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/clans/ClanRoleBadge.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/clans/MemberRow.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/players/PlayerCard.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/ui/StatusBadge.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | exact |
| `resources/js/components/ui/TabGroup.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/ui/Modal.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | role-match |
| `resources/js/components/ui/TextInput.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | exact |
| `resources/js/components/ui/Textarea.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | exact |
| `resources/js/components/ui/Select.vue` | Vue component | request-response | `resources/js/components/ui/Button.vue` | exact |
| `lang/en/clans.php` | i18n | — | `lang/en/admin.php` | role-match |
| `lang/en/players.php` | i18n | — | `lang/en/admin.php` | role-match |
| `routes/web.php` (modify) | route | request-response | `routes/web.php` | exact |
| `tests/Feature/Clans/ClanDirectoryTest.php` | test | request-response | `tests/Feature/Admin/FilamentResourcesPresentTest.php` | role-match |
| `tests/Feature/Clans/ClanShowTest.php` | test | request-response | `tests/Feature/Admin/FilamentResourcesPresentTest.php` | role-match |
| `tests/Feature/Clans/PlayerProfilePrivacyTest.php` | test | request-response | `tests/Feature/Auth/FirstLoginProvisioningTest.php` | role-match |
| `tests/Feature/Models/ClanMembershipModelTest.php` | test | CRUD | `tests/Feature/Models/PlayerPrivacyModelTest.php` | exact |
| `tests/Unit/Data/PlayerProfileDataTest.php` | test | request-response | `tests/Feature/Data/TypescriptTransformTest.php` | partial |
| `tests/Feature/Admin/ClanResourcesPresentTest.php` | test | request-response | `tests/Feature/Admin/FilamentResourcesPresentTest.php` | exact |
| `tests/Feature/Clans/DiscordGuildSeederTest.php` | test | CRUD | `tests/Feature/Auth/PermissionSeederTest.php` | role-match |

---

## Pattern Assignments

### Migrations

#### `database/migrations/…create_discord_guild_table.php` (migration, CRUD)

**Analog:** `apps/web/database/migrations/2026_05_03_100000_create_users_table.php`

**Full pattern** (lines 1-54):
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discord_guild', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // ...columns...
            $table->timestamps();
        });

        DB::statement('ALTER TABLE discord_guild ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE discord_guild ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE discord_guild ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_guild');
    }
};
```

**Key notes:**
- Always `declare(strict_types=1)` at top.
- Always set `id DEFAULT gen_random_uuid()` via raw SQL after `Schema::create`.
- Always cast `created_at`/`updated_at` to `timestamptz` via raw SQL.
- `discord_guild` table uses singular table name (not plural) — Eloquent default would be `discord_guilds`; override with `protected $table = 'discord_guild'` on the model.

---

#### `database/migrations/…create_clans_table.php` (migration, CRUD)

**Analog:** `apps/web/database/migrations/2026_05_03_100100_create_players_table.php`

**Soft deletes + CHECK constraint pattern** (lines 20-49):
```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('slug')->unique();
            $table->text('tag');           // 2–8 char short tag
            $table->text('name');
            $table->jsonb('description')->nullable();  // HasTranslations wraps this
            $table->text('country_code')->nullable();
            $table->uuid('owner_user_id');
            $table->text('status')->default('active');
            $table->text('discord_role_id')->nullable();
            $table->text('discord_announce_channel_id')->nullable();
            $table->timestamps();
            $table->softDeletes('deleted_at');

            $table->foreign('owner_user_id')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE clans ALTER COLUMN id SET DEFAULT gen_random_uuid();');
        DB::statement("ALTER TABLE clans ADD CONSTRAINT clans_status_check CHECK (status IN ('active','suspended','disbanded'));");
        DB::statement("ALTER TABLE clans ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
        DB::statement("ALTER TABLE clans ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
    }

    public function down(): void
    {
        Schema::dropIfExists('clans');
    }
};
```

**Copy from `create_players_table.php` lines 22-49:** The `jsonb` column, `softDeletes('deleted_at')`, `CHECK` constraint via `DB::statement`, `foreign()` reference pattern, and timestamptz upgrade calls.

---

#### `database/migrations/…create_clan_memberships_table.php` (migration, CRUD — partial unique index)

**Analog:** `apps/web/database/migrations/2026_05_03_100200_create_player_privacy_table.php` + RESEARCH.md Pattern 1

**Critical partial-index pattern** (from RESEARCH.md § Code Examples):
```php
public function up(): void
{
    Schema::create('clan_memberships', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('clan_id');
        $table->uuid('user_id');
        $table->text('role')->default('recruit');
        $table->timestampTz('joined_at');
        $table->timestampTz('left_at')->nullable();
        $table->uuid('invited_by')->nullable();
        $table->timestamps();

        $table->foreign('clan_id')->references('id')->on('clans')->restrictOnDelete();
        $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
    });

    DB::statement('ALTER TABLE clan_memberships ALTER COLUMN id SET DEFAULT gen_random_uuid();');
    DB::statement("ALTER TABLE clan_memberships ADD CONSTRAINT clan_memberships_role_check CHECK (role IN ('leader','officer','member','recruit'));");
    // D-009: at most one active membership per user — partial index, WHERE clause
    // not supported by Schema builder (Pitfall 1 in RESEARCH.md)
    DB::statement('CREATE UNIQUE INDEX clan_memberships_one_active ON clan_memberships (user_id) WHERE left_at IS NULL;');
}

public function down(): void
{
    DB::statement('DROP INDEX IF EXISTS clan_memberships_one_active;');
    Schema::dropIfExists('clan_memberships');
}
```

**Copy from `create_player_privacy_table.php` lines 22-49:** The `DB::statement` pattern for `ADD CONSTRAINT … CHECK`. The partial index must use `CREATE UNIQUE INDEX … WHERE` — never `$table->unique()`.

---

### Models

#### `app/Models/Clan.php` (model, CRUD)

**Analog:** `apps/web/app/Models/Player.php` (lines 1-76)

**Imports pattern** (lines 1-16):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;
```

**HasFactory PHPDoc + trait stack pattern** (lines 24-31 from Player.php, adapted):
```php
class Clan extends Model
{
    /** @use HasFactory<ClanFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;
    use LogsActivity;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['description'];
```

**LogsActivity options pattern** (lines 57-63 from Player.php):
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn (string $event): string => "Clan {$event}");
}
```

**Relation return-type PHPDoc pattern** (lines 65-76 from Player.php):
```php
/** @return BelongsTo<User, $this> */
public function owner(): BelongsTo
{
    return $this->belongsTo(User::class, 'owner_user_id');
}

/** @return BelongsToMany<ClanTag, $this> */
public function tags(): BelongsToMany
{
    return $this->belongsToMany(ClanTag::class, 'clan_clan_tag');
}

/** @return HasMany<ClanMembership, $this> */
public function memberships(): HasMany
{
    return $this->hasMany(ClanMembership::class);
}

/** @return HasMany<ClanMembership, $this> */
public function activeMembers(): HasMany
{
    return $this->hasMany(ClanMembership::class)->whereNull('left_at');
}
```

**Notes:**
- Add `HasTranslations` trait — NOT present on Player.php yet (Phase 2 adds it to Player too per RESEARCH.md Pitfall 3: remove `'bio' => 'array'` from `Player::casts()` when adding the trait).
- `/** @var list<string> */` PHPDoc is required on `$translatable` for PHPStan L8 (RESEARCH.md Pitfall 7).
- Do NOT add a `'description' => 'array'` cast — `HasTranslations` manages the JSONB accessor.

---

#### `app/Models/ClanTag.php` (model, CRUD)

**Analog:** `apps/web/app/Models/PlayerPrivacy.php` (lines 1-60)

**Minimal model pattern with HasTranslations** (from PlayerPrivacy.php structure):
```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasUuidPrimaryKey;
use Database\Factories\ClanTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Translatable\HasTranslations;

class ClanTag extends Model
{
    /** @use HasFactory<ClanTagFactory> */
    use HasFactory;

    use HasTranslations;
    use HasUuidPrimaryKey;

    /** @var list<string> */
    public array $translatable = ['label'];

    /** @var list<string> */
    protected $fillable = ['slug', 'label', 'color'];

    /** @return BelongsToMany<Clan, $this> */
    public function clans(): BelongsToMany
    {
        return $this->belongsToMany(Clan::class, 'clan_clan_tag');
    }
}
```

**Copy from `PlayerPrivacy.php` lines 1-60:** `declare(strict_types=1)`, namespace, `/** @use HasFactory<XFactory> */` PHPDoc, `HasUuidPrimaryKey` trait, `$fillable` list, `BelongsTo` relation with generic PHPDoc. Substitute `HasTranslations` for the relationship traits.

---

#### `app/Models/ClanMembership.php` (model, CRUD)

**Analog:** `apps/web/app/Models/PlayerPrivacy.php`

**Key differences from analog:**
- Adds `LogsActivity` (audit trail required for membership lifecycle per D-012).
- Adds `BelongsTo` relations to both `Clan` and `User`.
- No `HasTranslations` (no JSONB locale columns on this model).
- `$table = 'clan_memberships'` not needed (Eloquent default pluralizes correctly).

**LogsActivity pattern** — copy from `Player.php` lines 57-63 verbatim, substituting description.

---

#### `app/Models/ClanInvite.php` and `app/Models/ClanApplication.php` (models, CRUD)

**Analog:** `apps/web/app/Models/PlayerPrivacy.php`

Same minimal model structure. Both need:
- `LogsActivity` trait (state machine transitions must be audited — D-012).
- `$fillable` covering state (`status`) and FK columns.
- `BelongsTo` relations: `ClanInvite` → `Clan`, invited `User`, inviting `User`. `ClanApplication` → `Clan`, applicant `User`.
- Enum CHECK constraint enforcement is at DB level (migration) — model only validates via `$fillable`.

---

#### `app/Models/DiscordGuild.php` (model, CRUD)

**Analog:** `apps/web/app/Models/PlayerPrivacy.php`

```php
protected $table = 'discord_guild';  // singular — override Eloquent default
```

Copy `PlayerPrivacy.php` structure; remove `HasFactory` `/** @use HasFactory<...> */` PHPDoc only if no factory is created, add `LogsActivity` since guild config changes are admin-level events.

---

### Factories

#### `database/factories/ClanFactory.php` (factory, CRUD)

**Analog:** `apps/web/database/factories/PlayerFactory.php` (lines 1-37)

**Full factory pattern** (lines 1-37):
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Clan>
 */
class ClanFactory extends Factory
{
    protected $model = Clan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'owner_user_id' => User::factory(),
            'name'          => $name,
            'slug'          => Str::slug($name),
            'tag'           => strtoupper(Str::random(3)),
            'description'   => ['en' => fake()->sentence()],
            'country_code'  => fake()->countryCode(),
            'status'        => 'active',
        ];
    }
}
```

**Copy from `PlayerFactory.php` lines 1-37:** `declare(strict_types=1)`, namespace, `@extends Factory<Model>` PHPDoc, `$model` property, `definition()` return type `array<string, mixed>`, nested factory for FK (`User::factory()`), `Str::slug()` for slug generation.

---

#### `database/factories/ClanMembershipFactory.php` (factory, CRUD)

**Analog:** `apps/web/database/factories/PlayerPrivacyFactory.php` (lines 1-36)

**FK-references-parent pattern** (lines 1-36):
```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClanMembership>
 */
class ClanMembershipFactory extends Factory
{
    protected $model = ClanMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'clan_id'   => Clan::factory(),
            'user_id'   => User::factory(),
            'role'      => 'member',
            'joined_at' => now(),
            'left_at'   => null,
        ];
    }
}
```

**Copy from `PlayerPrivacyFactory.php` lines 1-36:** Structure is identical — parent factory as FK, typed defaults for all non-nullable columns.

---

### DTOs

#### `app/Data/ClanData.php` (DTO, request-response)

**Analog:** `apps/web/app/Data/PlayerData.php` (lines 1-34)

**Full DTO pattern** (lines 1-34):
```php
<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Source: .docs/05-database-schema.md § clans.
 *
 * `description` is a JSONB locale-keyed array via spatie/laravel-translatable.
 * In TS it surfaces as `Record<string, string> | null`.
 */
#[TypeScript]
final class ClanData extends Data
{
    /**
     * @param  array<string, string>|null  $description
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $tag,
        public string $name,
        public ?array $description,
        public ?string $country_code,
        public string $status,
        public ?string $discord_role_id,
    ) {}
}
```

**Copy from `PlayerData.php` lines 1-34:** `declare(strict_types=1)`, namespace `App\Data`, imports for `Data` + `TypeScript`, `#[TypeScript]` attribute, `final class … extends Data`, constructor-promotion, `/** @param array<string, string>|null */` for JSONB arrays.

---

#### `app/Data/PlayerProfileData.php` (DTO, request-response — privacy-aware)

**Analog:** `apps/web/app/Data/PlayerData.php`

This DTO differs from `PlayerData` in that it strips withheld fields entirely (not `null`) per RESEARCH.md Security Domain "absent vs null" note. The constructor receives only the fields the controller decides to include. Per RESEARCH.md Pattern 2:

```php
/**
 * Privacy-shaped player profile DTO — controller builds this after applying
 * show_to tier + per-section checks. Withheld sections are ABSENT from the DTO,
 * not null (prevents client-side enumeration via null-key presence check).
 *
 * @param  array<string, string>|null  $bio
 * @param  list<ClanMembershipData>|null  $clanHistory
 */
#[TypeScript]
final class PlayerProfileData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $displayName,
        public string $avatarUrl,
        public ?string $discordTag,       // null when show_discord_tag=false
        public ?string $countryCode,
        public ?array $bio,
        public ?ClanMembershipData $currentClan,
        public ?array $clanHistory,
        public bool $isOwnProfile,
    ) {}
}
```

**Copy from `PlayerData.php`:** All structural conventions. Key adaptation: `?string $discordTag` uses camelCase (TypeScript surface) rather than `snake_case`.

---

### Controllers

#### `app/Http/Controllers/ClanDirectoryController.php` (controller, request-response)

**Analog:** `apps/web/app/Http/Controllers/Auth/LogoutController.php` (lines 1-27)

**Single-action invokable controller pattern** (lines 1-27):
```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClanDirectoryController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // No auth required — public route
        return Inertia::render('Clans/Index', [
            // props built here
        ]);
    }
}
```

**Copy from `LogoutController.php` lines 1-27:** `declare(strict_types=1)`, namespace, extends `Controller`, single `__invoke` method, typed return, `use Inertia\Response` import.

---

#### `app/Http/Controllers/PlayerProfileController.php` (controller, request-response — privacy gate)

**Analog:** `apps/web/app/Http/Controllers/Auth/LogoutController.php` + RESEARCH.md Pattern 2

**Privacy gate core pattern** (from RESEARCH.md Pattern 2):
```php
public function __invoke(Player $player): Response
{
    $privacy = $player->privacy;
    $viewer  = auth()->user();

    // Step 1: global tier check
    match ($privacy->show_to) {
        'private'   => abort(404),
        'community' => auth()->check() ?: abort(404),
        'clan'      => $this->viewerInSameClan($viewer, $player) ?: abort(404),
        default     => null, // 'public' — allow through
    };

    // Step 2: per-section stripping — build DTO with only permitted fields
    return Inertia::render('Players/Show', [
        'player' => PlayerProfileData::fromModelWithPrivacy($player, $privacy, $viewer),
    ]);
}
```

**Route model binding:** The `Player $player` parameter triggers Laravel's route model binding by the `slug` route segment — add `getRouteKeyName(): string { return 'slug'; }` to the `Player` model.

---

#### `app/Http/Controllers/MyClan/MyClanController.php` (controller, request-response — auth gate)

**Analog:** `apps/web/app/Http/Controllers/Auth/LogoutController.php`

**My Clan access gate pattern** (from RESEARCH.md Pattern 7):
```php
public function __invoke(): Response
{
    $user = auth()->user();
    $membership = ClanMembership::where('user_id', $user->id)
        ->whereNull('left_at')
        ->with('clan')
        ->first();

    if (! $membership) {
        return Inertia::render('MyClan/Index', ['membership' => null, 'clan' => null]);
    }

    if (! in_array($membership->role, ['leader', 'officer'])) {
        return redirect()->route('clans.show', $membership->clan->slug);
    }

    return Inertia::render('MyClan/Index', [
        'membership' => ClanMembershipData::from($membership),
        'clan'       => ClanData::from($membership->clan),
    ]);
}
```

---

### Filament Resources

#### `app/Filament/Resources/ClanResource.php` (Filament resource, request-response)

**Analog:** `apps/web/app/Filament/Resources/PlayerResource.php` (lines 1-170)

**Imports pattern** (lines 1-17 from PlayerResource.php):
```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClanResource\Pages;
use App\Filament\Resources\ClanResource\RelationManagers;
use App\Models\Clan;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
```

**Tabs form structure** (lines 47-131 from PlayerResource.php):
```php
public static function form(Form $form): Form
{
    return $form->schema([
        Forms\Components\Tabs::make('clan_tabs')
            ->tabs([
                Forms\Components\Tabs\Tab::make(__('admin.tab.profile'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Section::make(__('admin.clan.section.profile'))
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('admin.clan.fields.name'))
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('slug')
                                    ->label(__('admin.clan.fields.slug'))
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('tag')
                                    ->label(__('admin.clan.fields.tag'))
                                    ->required()
                                    ->minLength(2)
                                    ->maxLength(8),
                                // KeyValue for JSONB description — same as player.bio pattern:
                                Forms\Components\KeyValue::make('description')
                                    ->label(__('admin.clan.fields.description'))
                                    ->keyLabel(__('admin.clan.fields.description_locale'))
                                    ->valueLabel(__('admin.clan.fields.description_text'))
                                    ->reorderable(false)
                                    ->default(['en' => ''])
                                    ->helperText(__('admin.clan.help.description_jsonb')),
                                Forms\Components\Select::make('tags')
                                    ->label(__('admin.clan.fields.tags'))
                                    ->multiple()
                                    ->relationship(titleAttribute: 'slug')
                                    ->preload(),
                            ]),
                    ]),

                Forms\Components\Tabs\Tab::make(__('admin.tab.audit'))
                    ->icon('heroicon-o-archive-box')
                    ->schema([
                        Forms\Components\Placeholder::make('audit_log')
                            ->label('')
                            ->content(fn ($record): View => view('filament.partials.audit-tab', [
                                'subject' => $record,
                            ])),
                    ]),
            ])
            ->columnSpanFull(),
    ]);
}
```

**KeyValue default pattern** — copy `->default(['en' => ''])` from PlayerResource.php line 87 area; Pitfall 6 in RESEARCH.md warns that empty KeyValue submits `null` instead of `{}`.

**getPages() with Create** (lines 160-169 from PlayerResource.php, adapted — Clan CAN be created from admin):
```php
/** @return array<string, PageRegistration> */
public static function getPages(): array
{
    return [
        'index'  => Pages\ListClans::route('/'),
        'create' => Pages\CreateClan::route('/create'),
        'view'   => Pages\ViewClan::route('/{record}'),
        'edit'   => Pages\EditClan::route('/{record}/edit'),
    ];
}
```

---

#### `app/Filament/Resources/DiscordGuildResource.php` (Filament resource, no-Create)

**Analog:** `apps/web/app/Filament/Resources/PermissionResource.php` (lines 1-90)

**No-Create getPages() pattern** (lines 81-88 from PermissionResource.php):
```php
/** @return array<string, PageRegistration> */
public static function getPages(): array
{
    return [
        'index' => Pages\ListDiscordGuilds::route('/'),
        // INTENTIONALLY no 'create' route — discord_guild holds exactly one row (D-003).
        // Seeder populates the row; admin fills fields via edit only.
        'edit'  => Pages\EditDiscordGuild::route('/{record}/edit'),
    ];
}
```

**Copy from `PermissionResource.php` lines 1-90:** Entire structure. Key adaptation: remove `disabled()`/`dehydrated(false)` from fields (guild fields ARE editable), retain the intentional comment explaining why no Create route exists.

---

#### `app/Filament/Resources/ClanResource/Pages/*.php` (Filament pages)

**Analog:** `apps/web/app/Filament/Resources/PlayerResource/Pages/EditPlayer.php` (lines 1-13)

**Minimal page class pattern** (lines 1-13):
```php
<?php

declare(strict_types=1);

namespace App\Filament\Resources\ClanResource\Pages;

use App\Filament\Resources\ClanResource;
use Filament\Resources\Pages\EditRecord;  // or ListRecords, CreateRecord, ViewRecord

class EditClan extends EditRecord
{
    protected static string $resource = ClanResource::class;
}
```

Copy verbatim — change only namespace, class name, and `extends` base class.

---

### Vue Pages

#### `resources/js/pages/Clans/Index.vue` (Vue page, request-response)

**Analog:** `apps/web/resources/js/pages/Home.vue` (lines 1-50)

**Script setup + layout + t() pattern** (lines 1-19 from Home.vue):
```vue
<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { useT } from '@/composables/useT';
import { Head } from '@inertiajs/vue3';
import type { ClanData } from '@/types/api';

const { t } = useT();

defineProps<{
    clans: ClanData[];
    tags: ClanTagData[];
}>();
</script>

<template>
    <Head :title="t('clans.directory.title')" />

    <PublicLayout>
        <!-- content — all strings via t() -->
    </PublicLayout>
</template>
```

**Copy from `Home.vue` lines 1-50:** `<script setup lang="ts">` block structure, `useT` import, `PublicLayout` wrap, `<Head :title="t(...)">` pattern, `defineProps<{...}>()` TypeScript generic syntax.

**Nav slot:** `PublicLayout` has `<slot name="nav" />` — Phase 2 populates it with links to `/clans` and `/players`. Wire via `<template #nav>` in each page.

---

#### `resources/js/pages/Players/Show.vue` (Vue page, request-response — privacy rendering)

**Analog:** `apps/web/resources/js/pages/Home.vue`

**Anti-pattern to avoid (from RESEARCH.md Pitfall 2):** Do NOT write `v-if="player.showDiscordTag"` — privacy stripping is backend-only. Instead write:

```vue
<template v-if="player.discordTag !== undefined">
    <span>{{ player.discordTag }}</span>
</template>
```

The prop is `undefined`/absent when withheld — never `null`. This is the "absent ≠ null" contract from RESEARCH.md Security Domain.

---

### Vue Components

#### `resources/js/components/ui/StatusBadge.vue`, `TextInput.vue`, `Textarea.vue`, `Select.vue` (Vue components)

**Analog:** `apps/web/resources/js/components/ui/Button.vue` (lines 1-50)

**Props + computed classes pattern** (lines 1-50 from Button.vue):
```vue
<script setup lang="ts">
import { computed } from 'vue';

type Variant = 'active' | 'suspended' | 'disbanded';  // StatusBadge example

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        // other typed props
    }>(),
    {
        variant: 'active',
    },
);

const variantClasses = computed(() => ({
    active:    'bg-green-100 text-green-800',
    suspended: 'bg-yellow-100 text-yellow-800',
    disbanded: 'bg-red-100 text-red-800',
})[props.variant]);
</script>

<template>
    <!-- use variantClasses, :class binding — NO hardcoded English text nodes -->
</template>
```

**Copy from `Button.vue` lines 1-50:** `withDefaults(defineProps<{...}>(), {...})` pattern, typed `type Variant = ...`, `computed()` for variant classes, CSS variable tokens (`var(--color-*)`) rather than raw Tailwind color classes.

---

### i18n Files

#### `lang/en/clans.php` (i18n)

**Analog:** `apps/web/lang/en/admin.php` (nested array structure with named sub-keys)

**File structure pattern** (lines 1-12 from admin.php):
```php
<?php

declare(strict_types=1);

/*
| Source: 02-UI-SPEC.md § Copywriting Contract — clan namespace strings.
*/

return [
    'directory' => [
        'title'       => 'Clans',
        'description' => 'Browse all clans in the league.',
    ],
    'show' => [
        'members_count' => ':count members',
    ],
    'my_clan' => [
        'heading'    => 'My Clan',
        'no_clan'    => 'You are not a member of any clan.',
        'create_cta' => 'Create your clan',
    ],
    // ... etc per UI-SPEC
];
```

**Copy from `admin.php`:** `declare(strict_types=1)`, docblock comment citing source, nested array with snake_case keys, `:param` interpolation tokens for dynamic strings.

---

### Routes

#### `routes/web.php` (modify, request-response)

**Analog:** `apps/web/routes/web.php` (lines 1-26) — existing file to extend

**Public + auth-gated group pattern** (lines 13-25 from web.php):
```php
// Public routes — no auth required (REQ-tenancy-multi-clan)
Route::get('/clans', ClanDirectoryController::class)->name('clans.index');
Route::get('/clans/{clan:slug}', ClanShowController::class)->name('clans.show');
Route::get('/players/{player:slug}', PlayerProfileController::class)->name('players.show');

// Authenticated routes
Route::middleware('auth')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');

    // Clan create
    Route::post('/clans', ClanCreateController::class)->name('clans.store');

    // My Clan management (auth + leadership check inside controller)
    Route::prefix('my-clan')->name('my-clan.')->group(function (): void {
        Route::get('/', MyClanController::class)->name('index');
        Route::post('/profile', [MyClanProfileController::class, 'update'])->name('profile.update');
        // ...etc
    });
});
```

**Copy from `web.php` lines 13-25:** `Route::middleware('auth')->group(...)` pattern, named routes (`->name()`), `Route::prefix()->name()->group()` for nested resource routes, controller class references (not `[Controller::class, 'method']` for single-action controllers).

---

### Tests

#### `tests/Feature/Admin/ClanResourcesPresentTest.php` (test, request-response)

**Analog:** `apps/web/tests/Feature/Admin/FilamentResourcesPresentTest.php` (lines 1-45)

**Filament resource presence test pattern** (lines 1-45):
```php
<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo('admin-access');
    $this->actingAs($this->admin);
});

it('registers ClanResource at /admin/clans', function (): void {
    $this->get('/admin/clans')->assertStatus(200);
});

it('does not expose Create for DiscordGuildResource (D-003)', function (): void {
    $this->get('/admin/discord-guilds/create')->assertStatus(404);
});
```

**Copy from `FilamentResourcesPresentTest.php` lines 1-45:** `beforeEach` seeder + admin setup, `$this->seed(PermissionSeeder::class)`, `->givePermissionTo('admin-access')`, `$this->actingAs($this->admin)`, `->assertStatus(200)` assertions.

---

#### `tests/Feature/Models/ClanMembershipModelTest.php` (test, CRUD)

**Analog:** `apps/web/tests/Feature/Models/PlayerPrivacyModelTest.php` (lines 1-29)

**DB constraint test pattern** (lines 16-19 from PlayerPrivacyModelTest.php):
```php
it('enforces show_to CHECK constraint', function (): void {
    expect(fn () => PlayerPrivacy::factory()->create(['show_to' => 'galactic']))
        ->toThrow(QueryException::class);
});
```

**Partial unique index test** (from RESEARCH.md Pattern 1 code example):
```php
it('enforces one active membership per user (D-009)', function (): void {
    $user  = User::factory()->create();
    $clan1 = Clan::factory()->create();
    $clan2 = Clan::factory()->create();

    ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan1->id,
        'left_at' => null,
    ]);

    expect(fn () => ClanMembership::factory()->create([
        'user_id' => $user->id,
        'clan_id' => $clan2->id,
        'left_at' => null,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
```

**Copy from `PlayerPrivacyModelTest.php` lines 1-29:** `declare(strict_types=1)`, global `use` imports, `it(...)` function style (no `class`), `expect(...)->toThrow(QueryException::class)` pattern.

---

#### `tests/Feature/Clans/PlayerProfilePrivacyTest.php` (test, request-response)

**Analog:** `apps/web/tests/Feature/Auth/FirstLoginProvisioningTest.php` (lines 1-133)

**Multi-scenario feature test pattern** (lines 39-59 from FirstLoginProvisioningTest.php):
```php
beforeEach(function (): void {
    // shared setup per scenario
});

it('returns 404 for show_to=private', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'private']), 'privacy')
        ->create();

    $this->get(route('players.show', $player->slug))->assertStatus(404);
});

it('returns 404 for show_to=community when guest', function (): void {
    $player = Player::factory()
        ->has(PlayerPrivacy::factory()->state(['show_to' => 'community']), 'privacy')
        ->create();

    $this->get(route('players.show', $player->slug))->assertStatus(404);
});
```

**Copy from `FirstLoginProvisioningTest.php` lines 39-59:** `beforeEach()` global setup, chained factory states (`->has(...)->state([...])`), `$this->get(route(...))->assertStatus(...)`, named routes via `route()` helper.

---

## Shared Patterns

### `declare(strict_types=1)` + namespace header

**Source:** Every PHP file in the codebase (e.g., `app/Models/Player.php` lines 1-6)
**Apply to:** ALL new PHP files — models, factories, migrations, controllers, DTOs, tests, seeders

```php
<?php

declare(strict_types=1);

namespace App\Models;  // (or appropriate namespace)
```

---

### HasUuidPrimaryKey trait

**Source:** Used by all Phase 1 models (`User.php` line 8, `Player.php` line 7, `PlayerPrivacy.php` line 7)
**Apply to:** All new Eloquent models (Clan, ClanTag, ClanMembership, ClanInvite, ClanApplication, DiscordGuild)

```php
use App\Concerns\HasUuidPrimaryKey;
// ...
use HasUuidPrimaryKey;
```

---

### LogsActivity trait + getActivitylogOptions()

**Source:** `app/Models/Player.php` lines 14-15, 57-63
**Apply to:** Clan, ClanMembership, ClanInvite, ClanApplication, DiscordGuild models

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
// ...
use LogsActivity;
// ...
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logFillable()
        ->logOnlyDirty()
        ->setDescriptionForEvent(fn (string $event): string => "Clan {$event}");
}
```

---

### HasFactory PHPDoc for PHPStan L8

**Source:** `app/Models/Player.php` line 27, `app/Models/User.php` line 37
**Apply to:** ALL new models

```php
/** @use HasFactory<ClanFactory> */
use HasFactory;
```

The `/** @use HasFactory<XFactory> */` PHPDoc is required — PHPStan L8 flags the trait without it.

---

### Filament Tabs + Audit Tab pattern

**Source:** `app/Filament/Resources/PlayerResource.php` lines 45-131, `app/Filament/Resources/UserResource.php` lines 43-89
**Apply to:** All Filament resources with LogsActivity models (ClanResource, ClanTagResource)

```php
Forms\Components\Tabs\Tab::make(__('admin.tab.audit'))
    ->icon('heroicon-o-archive-box')
    ->schema([
        Forms\Components\Placeholder::make('audit_log')
            ->label('')
            ->content(fn ($record): View => view('filament.partials.audit-tab', [
                'subject' => $record,
            ])),
    ]),
```

---

### KeyValue component for JSONB translatable fields

**Source:** `app/Filament/Resources/PlayerResource.php` lines 86-92
**Apply to:** ClanResource (description), ClanTagResource (label)

```php
Forms\Components\KeyValue::make('description')
    ->label(__('admin.clan.fields.description'))
    ->keyLabel(__('admin.clan.fields.description_locale'))
    ->valueLabel(__('admin.clan.fields.description_text'))
    ->reorderable(false)
    ->default(['en' => ''])           // Pitfall 6: empty KeyValue submits null without this
    ->helperText(__('admin.clan.help.description_jsonb')),
```

---

### Inertia::render return type

**Source:** `app/Http/Controllers/Auth/LogoutController.php` (redirect), controller pattern from RESEARCH.md Pattern 2
**Apply to:** All Inertia page controllers

```php
use Inertia\Inertia;
use Inertia\Response;
// ...
public function __invoke(): Response
{
    return Inertia::render('Clans/Index', [
        'clans' => ClanData::collect(Clan::all()),
    ]);
}
```

---

### Vue `useT()` + all strings via `t()`

**Source:** `resources/js/pages/Home.vue` lines 8-13, `resources/js/layouts/PublicLayout.vue` lines 3-8
**Apply to:** ALL new `.vue` files

```typescript
import { useT } from '@/composables/useT';
const { t } = useT();
```

Every visible string in `<template>` must be `{{ t('clans.some.key') }}`. NoHardcodedStringsTest (`tests/Feature/I18n/NoHardcodedStringsTest.php` lines 19-98) is a CI gate that auto-scans new `resources/js/pages/`, `resources/js/layouts/`, `resources/js/components/` directories.

---

### DB::statement timestamptz upgrade pattern

**Source:** `database/migrations/2026_05_03_100000_create_users_table.php` lines 43-47
**Apply to:** ALL new migrations

```php
DB::statement('ALTER TABLE clans ALTER COLUMN id SET DEFAULT gen_random_uuid();');
DB::statement("ALTER TABLE clans ALTER COLUMN created_at TYPE timestamptz USING created_at AT TIME ZONE 'UTC';");
DB::statement("ALTER TABLE clans ALTER COLUMN updated_at TYPE timestamptz USING updated_at AT TIME ZONE 'UTC';");
```

---

### Inertia DTO data flow (server-to-Vue)

**Source:** `app/Data/PlayerData.php` + `tests/Feature/Data/TypescriptTransformTest.php` lines 18-31
**Apply to:** All DTOs in `app/Data/`

- `#[TypeScript]` attribute on every DTO class triggers TypeScript type generation.
- After adding new DTOs, run `make artisan ARGS="typescript:transform"` to regenerate `resources/js/types/api.d.ts`.
- TypescriptTransformTest asserts the file is updated and contains expected type names — add a `toContain('ClanData')` assertion for each new DTO.

---

## No Analog Found

Files with no close match in the codebase — planner should use RESEARCH.md patterns and framework documentation instead:

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Filament/Resources/ClanResource/RelationManagers/MembersRelationManager.php` | Filament relation manager | CRUD | No RelationManager exists in Phase 1 codebase; use Filament v3 `RelationManager` base class pattern from Context7 `/filamentphp/filament` |
| `app/Policies/ClanPolicy.php` | policy | request-response | No Policy classes exist yet; use standard Laravel `php artisan make:policy` output pattern with `viewAny`, `view`, `create`, `update`, `delete` methods |
| `app/Policies/ClanMembershipPolicy.php` | policy | request-response | Same — no Policy analog |
| `app/Services/PlayerPrivacyGate.php` | service | request-response | No service layer classes exist in Phase 1; build as plain PHP class with static or constructor-injected dependencies |
| `app/Services/ClanSlugGenerator.php` | service | CRUD | Same — plain PHP class; use RESEARCH.md Pattern 5 for the collision-aware loop |
| `app/Services/ClanInviteService.php` | service | CRUD | Same — no service class analog; use RESEARCH.md Pattern 6 (state machine) |
| `app/Services/ClanApplicationService.php` | service | CRUD | Same |

---

## Metadata

**Analog search scope:** `apps/web/app/Models/`, `apps/web/app/Filament/Resources/`, `apps/web/app/Http/Controllers/`, `apps/web/app/Data/`, `apps/web/database/migrations/`, `apps/web/database/factories/`, `apps/web/resources/js/`, `apps/web/tests/`, `apps/web/lang/en/`, `apps/web/routes/`
**Files scanned:** 41 existing source files
**Pattern extraction date:** 2026-05-12

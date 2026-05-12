<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clans;

use App\Exceptions\ReservedSlugException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clans\StoreClanRequest;
use App\Models\Clan;
use App\Models\ClanMembership;
use App\Models\User;
use App\Services\ClanSlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Source: 02-09-PLAN.md Task 2.
 *
 * POST /clans — create a clan and assign the authenticated user as Leader.
 *
 * The creation is wrapped in a DB::transaction so the Clan row and the
 * ClanMembership row either both commit or both roll back (D-009 safety).
 * LogsActivity hooks on Eloquent's saved event — writes happen inside the
 * transaction so a rollback takes the audit log with it (T-02-05-08).
 *
 * Business rules enforced here (not in FormRequest):
 *  - Actor must NOT have an existing active membership (D-009 one-active rule).
 *  - Slug must not be reserved (ClanSlugGenerator::generate throws ReservedSlugException).
 */
class ClanCreateController extends Controller
{
    public function __invoke(StoreClanRequest $request, ClanSlugGenerator $slugger): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // D-009: reject if actor already has an active membership.
        $alreadyMember = ClanMembership::where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();

        if ($alreadyMember) {
            abort(409, __('clans.create.already_member'));
        }

        try {
            $slug = $slugger->generate($request->validated('name'));
        } catch (ReservedSlugException) {
            throw ValidationException::withMessages([
                'name' => [__('clans.form.name.errors.reserved')],
            ]);
        }

        DB::transaction(function () use ($request, $slug, $user): void {
            $clan = Clan::create([
                'slug' => $slug,
                'tag' => $request->validated('tag'),
                'name' => $request->validated('name'),
                'description' => $request->validated('description') !== null
                    ? ['en' => $request->validated('description')]
                    : null,
                'country_code' => $request->validated('country_code'),
                'owner_user_id' => $user->id,
                'status' => 'active',
            ]);

            ClanMembership::create([
                'clan_id' => $clan->id,
                'user_id' => $user->id,
                'role' => 'leader',
                'joined_at' => now(),
                'left_at' => null,
            ]);
        });

        return redirect()->route('my-clan.index')->with('success', __('clans.create.success'));
    }
}

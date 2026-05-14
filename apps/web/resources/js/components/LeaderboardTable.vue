<!-- Source: .planning/phases/09-polish/09-06-PLAN.md task 2.
     Stateless table renderer for both top-players and top-clans tabs.
     D-018: players with is_anonymous=true render without name link or clan. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type LeaderboardEntryData = App.Data.LeaderboardEntryData;
type LeaderboardClanEntryData = App.Data.LeaderboardClanEntryData;

interface Props {
    rows: (LeaderboardEntryData | LeaderboardClanEntryData)[];
    mode: 'players' | 'clans';
}

const props = defineProps<Props>();

const { t } = useT();

const isPlayerMode = computed<boolean>(() => props.mode === 'players');
const hasRows = computed<boolean>(() => props.rows.length !== 0);

// Type guards for the discriminated union.
function asPlayer(row: LeaderboardEntryData | LeaderboardClanEntryData): LeaderboardEntryData {
    return row as LeaderboardEntryData;
}

function asClan(row: LeaderboardEntryData | LeaderboardClanEntryData): LeaderboardClanEntryData {
    return row as LeaderboardClanEntryData;
}

/**
 * Format KDR per plan spec:
 *   null     → "—"
 *   Infinity → "∞" (deaths sum to zero with non-zero kills — service emits null
 *              for zero deaths via NULLIF, but we keep the Infinity branch as
 *              defence-in-depth)
 *   else     → .toFixed(2)
 */
function formatKdr(kdr: number | null): string {
    if (kdr === null) {
        return '—';
    }
    if (!Number.isFinite(kdr)) {
        return '∞';
    }
    return kdr.toFixed(2);
}
</script>

<template>
    <div
        v-if="!hasRows"
        class="py-12 text-center text-sm text-[var(--color-text-muted)]
               border border-dashed border-[var(--color-border)] rounded-md"
    >
        {{ t('leaderboards.empty_state') }}
    </div>

    <table v-else class="w-full text-sm">
        <thead>
            <tr class="border-b border-[var(--color-border)] text-left text-[var(--color-text-muted)]">
                <th class="py-2 px-3 font-semibold w-12">{{ t('leaderboards.columns.rank') }}</th>

                <template v-if="isPlayerMode">
                    <th class="py-2 px-3 font-semibold">{{ t('leaderboards.columns.player') }}</th>
                    <th class="py-2 px-3 font-semibold">{{ t('leaderboards.columns.clan') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.kills') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.deaths') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.kdr') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.matches') }}</th>
                </template>

                <template v-else>
                    <th class="py-2 px-3 font-semibold">{{ t('leaderboards.columns.clan') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.kills') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.wins') }}</th>
                    <th class="py-2 px-3 font-semibold text-right">{{ t('leaderboards.columns.matches') }}</th>
                </template>
            </tr>
        </thead>
        <tbody>
            <template v-if="isPlayerMode">
                <tr
                    v-for="(row, index) in rows"
                    :key="`p-${index}`"
                    class="border-b border-[var(--color-border)] hover:bg-[var(--color-surface-elevated)]"
                >
                    <td class="py-2 px-3 text-[var(--color-text-muted)]">{{ index + 1 }}</td>
                    <td class="py-2 px-3">
                        <!-- D-018: anonymous rows render the i18n label and DO NOT wrap in <Link>. -->
                        <span
                            v-if="asPlayer(row).is_anonymous"
                            class="text-[var(--color-text-muted)] italic"
                        >
                            {{ asPlayer(row).player_name }}
                        </span>
                        <Link
                            v-else
                            :href="`/players/${asPlayer(row).player_id}`"
                            class="font-semibold text-[var(--color-text)] hover:text-[var(--color-accent)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        >
                            {{ asPlayer(row).player_name }}
                        </Link>
                    </td>
                    <td class="py-2 px-3 text-[var(--color-text-muted)]">
                        {{ asPlayer(row).clan_name ?? '—' }}
                    </td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asPlayer(row).kills }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asPlayer(row).deaths }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ formatKdr(asPlayer(row).kdr) }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asPlayer(row).matches_played }}</td>
                </tr>
            </template>

            <template v-else>
                <tr
                    v-for="(row, index) in rows"
                    :key="`c-${index}`"
                    class="border-b border-[var(--color-border)] hover:bg-[var(--color-surface-elevated)]"
                >
                    <td class="py-2 px-3 text-[var(--color-text-muted)]">{{ index + 1 }}</td>
                    <td class="py-2 px-3">
                        <Link
                            :href="`/clans/${asClan(row).clan_slug}`"
                            class="font-semibold text-[var(--color-text)] hover:text-[var(--color-accent)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        >
                            {{ asClan(row).clan_name }}
                        </Link>
                    </td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asClan(row).kills }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asClan(row).wins }}</td>
                    <td class="py-2 px-3 text-right tabular-nums">{{ asClan(row).matches_played }}</td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

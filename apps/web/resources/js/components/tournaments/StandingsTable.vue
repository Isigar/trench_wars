<!-- Source: 06-12-PLAN.md Task 2 — StandingsTable. Format-aware tiebreak column:
     - swiss → Buchholz
     - round_robin → Point diff
     - elimination formats → generic Tiebreak label -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type TournamentStandingData = App.Data.TournamentStandingData;

const { t } = useT();

const props = defineProps<{
    standings: TournamentStandingData[] | null;
    format: string;
}>();

const hasStandings = computed<boolean>(
    () => Array.isArray(props.standings) && props.standings.length >= 1,
);

const rows = computed<TournamentStandingData[]>(() => props.standings ?? []);

const tiebreakLabel = computed<string>(() => {
    if (props.format === 'swiss') return t('tournaments.standings.tiebreak_buchholz');
    if (props.format === 'round_robin') return t('tournaments.standings.tiebreak_point_diff');
    return t('tournaments.standings.tiebreak_default');
});

// Inertia + spatie/laravel-data attach the eager-loaded clan name on the
// participant relation, but only when the controller loads
// standings.participant.clan. The DTO doesn't expose it directly — we permissively
// read off the row via an indexed accessor with a fallback.
function clanLabel(row: TournamentStandingData): string {
    type StandingWithParticipant = TournamentStandingData & {
        participant?: { clan?: { name?: string } | null; clan_name?: string | null };
        clan_name?: string | null;
    };
    const r = row as StandingWithParticipant;
    return (
        r.participant?.clan?.name ??
        r.participant?.clan_name ??
        r.clan_name ??
        '—'
    );
}

function rankLabel(row: TournamentStandingData): string {
    return row.rank !== null ? String(row.rank) : '—';
}
</script>

<template>
    <div v-if="hasStandings" class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-[var(--color-border)]">
                    <th class="text-left px-3 py-2 font-semibold">{{ t('tournaments.standings.rank') }}</th>
                    <th class="text-left px-3 py-2 font-semibold">{{ t('tournaments.standings.clan') }}</th>
                    <th class="text-right px-3 py-2 font-semibold">{{ t('tournaments.standings.wins') }}</th>
                    <th class="text-right px-3 py-2 font-semibold">{{ t('tournaments.standings.losses') }}</th>
                    <th class="text-right px-3 py-2 font-semibold">{{ t('tournaments.standings.draws') }}</th>
                    <th class="text-right px-3 py-2 font-semibold">{{ t('tournaments.standings.points') }}</th>
                    <th class="text-right px-3 py-2 font-semibold">{{ tiebreakLabel }}</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-b border-[var(--color-border)]"
                >
                    <td class="px-3 py-2">{{ rankLabel(row) }}</td>
                    <td class="px-3 py-2">{{ clanLabel(row) }}</td>
                    <td class="px-3 py-2 text-right">{{ row.wins }}</td>
                    <td class="px-3 py-2 text-right">{{ row.losses }}</td>
                    <td class="px-3 py-2 text-right">{{ row.draws }}</td>
                    <td class="px-3 py-2 text-right">{{ row.points }}</td>
                    <td class="px-3 py-2 text-right">{{ row.tiebreak_score }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div v-else role="status" class="py-12 text-center">
        <p class="text-base text-[var(--color-text-muted)]">
            {{ t('tournaments.empty.standings') }}
        </p>
    </div>
</template>

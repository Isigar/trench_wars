<!-- Source: 06-12-PLAN.md Task 2 — TournamentScheduleList. Lists materialised
     brackets (match_id NOT NULL) with scheduled_at + a "View match" link.
     Reads from PublicTournamentData.nodes which is BracketNodeData[]. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type BracketNodeData = App.Data.BracketNodeData;

const { t } = useT();

const props = defineProps<{
    nodes: BracketNodeData[];
}>();

// Only show brackets that have been materialised (match_id is set) — pending
// brackets don't yet have a scheduled match to link to.
const scheduled = computed<BracketNodeData[]>(() =>
    props.nodes.filter((n) => n.match_id !== null),
);

const hasSchedule = computed<boolean>(() => scheduled.value.length >= 1);

function dateLabel(iso: string | null): string {
    if (iso === null) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
}

function pairingLabel(node: BracketNodeData): string {
    const a = node.participant_a?.clan_name ?? '—';
    const b = node.participant_b?.clan_name ?? '—';
    return `${a} vs ${b}`;
}

function matchHref(matchId: string | null): string {
    return matchId === null ? '#' : `/matches/${matchId}`;
}
</script>

<template>
    <div v-if="hasSchedule" class="flex flex-col gap-2">
        <div
            v-for="node in scheduled"
            :key="node.id"
            class="flex items-center justify-between px-3 py-2 border border-[var(--color-border)] rounded-md"
        >
            <div class="flex flex-col">
                <span class="text-base font-semibold text-[var(--color-text)]">{{ pairingLabel(node) }}</span>
                <time class="text-sm text-[var(--color-text-muted)]">{{ dateLabel(node.scheduled_at) }}</time>
            </div>
            <Link
                v-if="node.match_id"
                :href="matchHref(node.match_id)"
                class="text-sm font-semibold text-[var(--color-accent)] hover:opacity-90 focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            >
                {{ t('tournaments.show.schedule_view_match') }}
            </Link>
        </div>
    </div>
    <div v-else role="status" class="py-12 text-center">
        <p class="text-base text-[var(--color-text-muted)]">
            {{ t('tournaments.show.schedule_empty') }}
        </p>
    </div>
</template>

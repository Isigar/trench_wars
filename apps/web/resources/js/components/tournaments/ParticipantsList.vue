<!-- Source: 06-12-PLAN.md Task 2 — ParticipantsList. Reads from
     PublicTournamentData.participants (privacy-filtered DTO). Each row shows
     clan name + seed (or "Unseeded" fallback) + status. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type TournamentParticipantData = App.Data.TournamentParticipantData;

const { t } = useT();

const props = defineProps<{
    participants: TournamentParticipantData[] | null;
}>();

const hasParticipants = computed<boolean>(
    () => Array.isArray(props.participants) && props.participants.length >= 1,
);

const rows = computed<TournamentParticipantData[]>(() => props.participants ?? []);

function seedLabel(p: TournamentParticipantData): string {
    if (p.seed === null) return t('tournaments.participants.no_seed');
    return `${t('tournaments.participants.seed_label')} #${p.seed}`;
}

function statusLabel(p: TournamentParticipantData): string {
    return t(`tournaments.participant_status.${p.status}.label`);
}

function clanName(p: TournamentParticipantData): string {
    return p.clan_name ?? '—';
}
</script>

<template>
    <div v-if="hasParticipants" class="flex flex-col gap-2">
        <div
            v-for="p in rows"
            :key="p.id"
            class="flex items-center justify-between px-3 py-2 border border-[var(--color-border)] rounded-md"
        >
            <div class="flex flex-col">
                <span class="text-base font-semibold text-[var(--color-text)]">{{ clanName(p) }}</span>
                <span class="text-sm text-[var(--color-text-muted)]">{{ seedLabel(p) }}</span>
            </div>
            <span class="text-sm text-[var(--color-text-muted)]">{{ statusLabel(p) }}</span>
        </div>
    </div>
    <div v-else role="status" class="py-12 text-center">
        <p class="text-base text-[var(--color-text-muted)]">
            {{ t('tournaments.empty.participants') }}
        </p>
    </div>
</template>

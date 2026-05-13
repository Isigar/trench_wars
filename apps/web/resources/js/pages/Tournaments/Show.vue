<!-- Source: 06-12-PLAN.md Task 2 + 06-RESEARCH.md Pattern 9 (30s polling).
     5-tab public tournament page: Overview / Bracket / Schedule / Standings /
     Participants. Hydrates polled data via useTournamentPolling composable. -->
<script setup lang="ts">
import BracketCanvas from '@/components/tournaments/BracketCanvas.vue';
import ParticipantsList from '@/components/tournaments/ParticipantsList.vue';
import StandingsTable from '@/components/tournaments/StandingsTable.vue';
import TournamentScheduleList from '@/components/tournaments/TournamentScheduleList.vue';
import { useT } from '@/composables/useT';
import { useTournamentPolling } from '@/composables/useTournamentPolling';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type PublicTournamentData = App.Data.PublicTournamentData;

const { t } = useT();

const props = defineProps<{
    tournament: PublicTournamentData;
}>();

type Tab = 'overview' | 'bracket' | 'schedule' | 'standings' | 'participants';
const activeTab = ref<Tab>('overview');

// Polling: replace the local snapshot with the polled tournament when an update arrives.
const polled = ref<PublicTournamentData>(props.tournament);
useTournamentPolling(props.tournament.slug, polled);

const titleText = computed<string>(
    () => polled.value.title?.en ?? t('tournaments.show.title_fallback'),
);

const formatLabel = computed<string>(() =>
    t(`tournaments.formats.${polled.value.format}.label`),
);

const statusLabel = computed<string>(() =>
    t(`tournaments.status.${polled.value.status}.label`),
);

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

function setTab(tab: Tab): void {
    activeTab.value = tab;
}

function tabButtonClass(tab: Tab): string[] {
    const base = [
        'px-3 py-1 text-sm font-semibold rounded-md',
        'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
        'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
    ];
    const active = activeTab.value === tab
        ? 'text-[var(--color-text)] border-l-[3px] border-[var(--color-accent)] pl-2'
        : 'text-[var(--color-text-muted)] hover:text-[var(--color-text)]';
    return [...base, active];
}
</script>

<template>
    <Head :title="titleText" />

    <PublicLayout>
        <section class="max-w-5xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ titleText }}
                </h1>
                <div class="flex flex-wrap items-center gap-3 text-sm text-[var(--color-text-muted)]">
                    <span>{{ formatLabel }}</span>
                    <span>·</span>
                    <span>{{ statusLabel }}</span>
                </div>
            </header>

            <nav class="flex flex-wrap gap-2" :aria-label="t('tournaments.tabs.overview.label')">
                <button
                    type="button"
                    :class="tabButtonClass('overview')"
                    :aria-current="activeTab === 'overview' ? 'page' : undefined"
                    @click="setTab('overview')"
                >{{ t('tournaments.tabs.overview.label') }}</button>
                <button
                    type="button"
                    :class="tabButtonClass('bracket')"
                    :aria-current="activeTab === 'bracket' ? 'page' : undefined"
                    @click="setTab('bracket')"
                >{{ t('tournaments.tabs.bracket.label') }}</button>
                <button
                    type="button"
                    :class="tabButtonClass('schedule')"
                    :aria-current="activeTab === 'schedule' ? 'page' : undefined"
                    @click="setTab('schedule')"
                >{{ t('tournaments.tabs.schedule.label') }}</button>
                <button
                    type="button"
                    :class="tabButtonClass('standings')"
                    :aria-current="activeTab === 'standings' ? 'page' : undefined"
                    @click="setTab('standings')"
                >{{ t('tournaments.tabs.standings.label') }}</button>
                <button
                    type="button"
                    :class="tabButtonClass('participants')"
                    :aria-current="activeTab === 'participants' ? 'page' : undefined"
                    @click="setTab('participants')"
                >{{ t('tournaments.tabs.participants.label') }}</button>
            </nav>

            <div>
                <div v-if="activeTab === 'overview'" class="flex flex-col gap-3">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div class="flex flex-col">
                            <dt class="text-[var(--color-text-muted)]">{{ t('tournaments.show.format_label') }}</dt>
                            <dd class="text-[var(--color-text)]">{{ formatLabel }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[var(--color-text-muted)]">{{ t('tournaments.show.status_label') }}</dt>
                            <dd class="text-[var(--color-text)]">{{ statusLabel }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[var(--color-text-muted)]">{{ t('tournaments.show.starts_label') }}</dt>
                            <dd class="text-[var(--color-text)]">{{ dateLabel(polled.starts_at) }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[var(--color-text-muted)]">{{ t('tournaments.show.ends_label') }}</dt>
                            <dd class="text-[var(--color-text)]">{{ dateLabel(polled.ends_at) }}</dd>
                        </div>
                        <div class="flex flex-col">
                            <dt class="text-[var(--color-text-muted)]">{{ t('tournaments.show.participants_label') }}</dt>
                            <dd class="text-[var(--color-text)]">{{ polled.participant_count }}</dd>
                        </div>
                    </dl>
                </div>

                <div v-else-if="activeTab === 'bracket'">
                    <BracketCanvas :nodes="polled.nodes" :edges="polled.edges" />
                </div>

                <div v-else-if="activeTab === 'schedule'">
                    <TournamentScheduleList :nodes="polled.nodes" />
                </div>

                <div v-else-if="activeTab === 'standings'">
                    <StandingsTable :standings="polled.standings" :format="polled.format" />
                </div>

                <div v-else>
                    <ParticipantsList :participants="polled.participants" />
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

<!-- Source: 06-12-PLAN.md Task 2 — public tournament directory page.
     Mirrors the Matches/Index.vue idiom (Phase 4 plan 04-11): PublicLayout wrap,
     section heading, list of cards. No filter bar in v1 (Phase 9 polish). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useT();

interface TournamentRow {
    id: string;
    slug: string;
    title: Record<string, string> | null;
    format: string;
    status: string;
    starts_at: string | null;
    ends_at: string | null;
    max_participants: number | null;
}

const props = defineProps<{
    tournaments: TournamentRow[];
}>();

const hasTournaments = computed<boolean>(() => props.tournaments.length >= 1);

function titleText(t: TournamentRow): string {
    return t.title?.en ?? t.slug;
}

function dateLabel(iso: string | null): string {
    if (iso === null) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(d);
}

function formatLabel(format: string): string {
    return t(`tournaments.formats.${format}.label`);
}

function statusLabel(status: string): string {
    return t(`tournaments.status.${status}.label`);
}
</script>

<template>
    <Head :title="t('tournaments.directory.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8">
            <div class="flex flex-col gap-4">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('tournaments.directory.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('tournaments.directory.subtitle') }}
                </p>

                <div class="mt-2">
                    <div
                        v-if="!hasTournaments"
                        role="status"
                        class="py-12 text-center"
                    >
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('tournaments.directory.empty_default') }}
                        </p>
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <Link
                            v-for="tournament in tournaments"
                            :key="tournament.id"
                            :href="`/tournaments/${tournament.slug}`"
                            class="flex flex-col gap-2 px-4 py-3 border border-[var(--color-border)]
                                   rounded-md bg-[var(--color-surface)]
                                   hover:bg-[var(--color-surface-elevated)]
                                   transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        >
                            <span class="text-lg font-semibold text-[var(--color-text)]">
                                {{ titleText(tournament) }}
                            </span>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-[var(--color-text-muted)]">
                                <span>{{ formatLabel(tournament.format) }}</span>
                                <span>·</span>
                                <span>{{ statusLabel(tournament.status) }}</span>
                                <span v-if="tournament.starts_at !== null">·</span>
                                <span v-if="tournament.starts_at !== null">
                                    {{ t('tournaments.directory.card_starts_label') }} {{ dateLabel(tournament.starts_at) }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

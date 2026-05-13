<!-- Source: 04-11-PLAN.md Task 1 + 04-RESEARCH.md § Pattern 7 (MatchCard spec). -->
<script setup lang="ts">
import EventDateBadge from '@/components/events/EventDateBadge.vue';
import MatchStatusBadge from '@/components/matches/MatchStatusBadge.vue';
import { useT } from '@/composables/useT';
import { computed } from 'vue';

const { t } = useT();

type PublicMatchData = App.Data.PublicMatchData;

/**
 * The calendar query in MatchCalendarController eager-loads `slots`, so
 * paginator items arrive with a `slots` collection alongside the canonical
 * PublicMatchData shape. We model that defensively — the prop type is
 * PublicMatchData (the strongly-typed contract) plus a permissive overlay
 * for the optional eager-loaded slots used purely to render the signup
 * summary "X / Y signed up". Absent overlay → summary section hidden.
 */
type CalendarMatchEntry = PublicMatchData & {
    slots?: Array<{ occupant_user_id?: string | null }>;
};

const props = defineProps<{
    match: CalendarMatchEntry;
}>();

const titleText = computed<string>(() => {
    return props.match.title?.en ?? t('matches.show.title_fallback');
});

const descriptionExcerpt = computed<string>(() => {
    const desc = props.match.description?.en ?? '';
    if (desc.length <= 80) return desc;
    return desc.substring(0, 80) + '…';
});

const signupSummary = computed<string | null>(() => {
    const slots = props.match.slots;
    if (!Array.isArray(slots) || slots.length === 0) return null;
    const occupied = slots.filter((s) => s.occupant_user_id !== null && s.occupant_user_id !== undefined).length;
    return t('matches.directory.signup_summary', {
        occupied,
        total: slots.length,
    });
});

const matchHref = computed<string>(() => `/matches/${props.match.id}`);
</script>

<template>
    <a
        :href="matchHref"
        class="flex items-start gap-4 p-4 rounded-lg
               bg-[var(--color-surface)] border border-[var(--color-border)]
               hover:bg-[var(--color-surface-elevated)] hover:border-[var(--color-accent)]
               transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
    >
        <!-- Date pill -->
        <EventDateBadge :starts-at="match.scheduled_at" />

        <div class="flex-1 min-w-0 flex flex-col gap-2">
            <!-- Title + status row -->
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-xl font-semibold text-[var(--color-text)] leading-tight truncate">
                    {{ titleText }}
                </h2>
                <MatchStatusBadge :status="match.status" />
            </div>

            <!-- Description excerpt -->
            <p
                v-if="descriptionExcerpt"
                class="text-base text-[var(--color-text-muted)] line-clamp-2"
            >
                {{ descriptionExcerpt }}
            </p>

            <!-- Signup count summary (only when eager-loaded slots are present) -->
            <p
                v-if="signupSummary"
                class="text-sm text-[var(--color-text-muted)]"
            >
                {{ signupSummary }}
            </p>
        </div>
    </a>
</template>

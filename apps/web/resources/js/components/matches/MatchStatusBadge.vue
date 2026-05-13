<!-- Source: 04-11-PLAN.md Task 2 + 04-RESEARCH.md § Pattern 7 (5 status variants). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type MatchStatus = 'draft' | 'open' | 'locked' | 'played' | 'cancelled';

const { t } = useT();

const props = defineProps<{
    /** Match status from PublicMatchData.status (string, narrowed to the 5 known states). */
    status: MatchStatus | string;
}>();

// Visual variant map — 5 match-domain statuses mapped to color-mix opacity
// surfaces (consistent with Phase 1/2 StatusBadge `pending/public/private` pattern).
const variantMap: Record<MatchStatus, { bg: string; fg: string }> = {
    draft: {
        bg: 'color-mix(in srgb, var(--color-text-muted) 20%, transparent)',
        fg: 'var(--color-text-muted)',
    },
    open: {
        bg: 'color-mix(in srgb, var(--color-success) 20%, transparent)',
        fg: 'var(--color-success)',
    },
    locked: {
        bg: 'color-mix(in srgb, var(--color-warning) 20%, transparent)',
        fg: 'var(--color-warning)',
    },
    played: {
        bg: 'color-mix(in srgb, var(--color-accent) 20%, transparent)',
        fg: 'var(--color-accent)',
    },
    cancelled: {
        bg: 'color-mix(in srgb, var(--color-danger) 20%, transparent)',
        fg: 'var(--color-danger)',
    },
};

const inlineStyle = computed<{ backgroundColor: string; color: string }>(() => {
    const v = variantMap[props.status as MatchStatus] ?? variantMap.draft;
    return { backgroundColor: v.bg, color: v.fg };
});

// Labels via t('matches.status.label.{status}'); unknown status falls through to the raw string.
const label = computed<string>(() => {
    const key = `matches.status.label.${props.status}`;
    return t(key);
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center px-2 py-0.5 rounded-sm',
            'text-sm font-semibold uppercase tracking-wide',
        ]"
        :style="inlineStyle"
    >{{ label }}</span>
</template>

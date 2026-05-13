<!-- Source: 04-11-PLAN.md Task 3 + 04-RESEARCH.md § Pattern 7 — reusable across Match (Phase 4),
     Tournament cards (Phase 6), and Article cards (Phase 7).
     dayjs is NOT in the dependency tree; we use the native Intl API to avoid adding a runtime dep. -->
<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    /** ISO-8601 datetime string from EventData.starts_at or GameMatch.scheduled_at. */
    startsAt: string;
}>();

// Parse defensively — an invalid ISO string yields an Invalid Date object;
// Intl.DateTimeFormat will throw RangeError on it, so we guard.
const date = computed<Date | null>(() => {
    const d = new Date(props.startsAt);
    return Number.isNaN(d.getTime()) ? null : d;
});

const monthLabel = computed<string>(() => {
    if (date.value === null) return '—';
    // en-US locale produces "Jan", "Feb", …; uppercase to match calendar-pill aesthetic.
    return new Intl.DateTimeFormat('en-US', { month: 'short' })
        .format(date.value)
        .toUpperCase();
});

const dayLabel = computed<string>(() => {
    if (date.value === null) return '—';
    return String(date.value.getDate());
});

const isoLabel = computed<string>(() => date.value?.toISOString() ?? props.startsAt);
</script>

<template>
    <time
        :datetime="isoLabel"
        class="inline-flex flex-col items-center justify-center
               min-w-8 min-h-8 px-2 py-1 rounded-md
               bg-[var(--color-accent)] text-[var(--color-accent-fg)]
               font-mono text-xs font-semibold leading-tight select-none"
        aria-hidden="false"
    >
        <span class="text-[10px] tracking-wide">{{ monthLabel }}</span>
        <span class="text-base leading-none">{{ dayLabel }}</span>
    </time>
</template>

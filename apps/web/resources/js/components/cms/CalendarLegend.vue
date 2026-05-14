<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 33 + <interfaces>
     "Open Question 6 LOCKED color scheme".

     CalendarLegend renders 3 colored chips matching CalendarEventData::colourFor
     (07-09 D-07-09-D). The hex values are inlined here so the legend never
     drifts from the calendar painting; if the palette changes, both files must
     update in lockstep (07-VALIDATION snapshot test catches drift). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';

const { t } = useT();

interface LegendChip {
    type: 'match' | 'tournament' | 'article';
    color: string;
    labelKey: string;
}

// Hex values mirror CalendarEventData::colourFor (07-09 D-07-09-D).
//   match=#3B82F6 (Tailwind blue-500), tournament=#8B5CF6 (Tailwind violet-500),
//   article=#10B981 (Tailwind emerald-500).
const chips: LegendChip[] = [
    { type: 'match', color: '#3B82F6', labelKey: 'events.legend.match.label' },
    { type: 'tournament', color: '#8B5CF6', labelKey: 'events.legend.tournament.label' },
    { type: 'article', color: '#10B981', labelKey: 'events.legend.article.label' },
];
</script>

<template>
    <ul
        class="flex flex-wrap items-center gap-4 text-xs text-[var(--color-text-muted)] mb-4"
        :aria-label="t('events.header.title')"
    >
        <li
            v-for="chip in chips"
            :key="chip.type"
            class="inline-flex items-center gap-2"
            :data-test="`calendar-legend-${chip.type}`"
        >
            <span
                class="inline-block w-3 h-3 rounded-sm"
                :style="{ backgroundColor: chip.color }"
                aria-hidden="true"
            ></span>
            <span>{{ t(chip.labelKey) }}</span>
        </li>
    </ul>
</template>

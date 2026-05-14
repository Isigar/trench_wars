<!-- Source: 06-12-PLAN.md Task 2 + 06-RESEARCH.md Pattern 8 (SVG bracket renderer).
     One bracket cell — rendered as an SVG <g> with rect background + 2 participant
     text rows. Pure presentational; the parent BracketCanvas positions us via the
     :transform binding. Status-driven fill via CSS variables (Phase 1 palette). -->
<script setup lang="ts">
import { NODE_HEIGHT, NODE_WIDTH } from '@/components/tournaments/bracket-node-dimensions';
import { computed } from 'vue';

// Plan 07-10 Rule 3 fix: the NODE_WIDTH/NODE_HEIGHT constants previously lived
// inline in this <script setup> as `export const` declarations. Vue's
// compiler-sfc 3.5+ refuses module-level exports inside <script setup>
// (the script-setup RFC enforces component-scope only). Constants now live in
// sibling bracket-node-dimensions.ts and are re-imported here for the template
// bindings; BracketCanvas.vue imports the same module directly.
type BracketNodeData = App.Data.BracketNodeData;

const props = defineProps<{
    node: BracketNodeData;
}>();

const fillColor = computed<string>(() =>
    props.node.status === 'completed'
        ? 'var(--color-bracket-node-completed)'
        : 'var(--color-bracket-node-pending)',
);

// Display the clan name from participant_a / participant_b; if absent, show TBD.
// We deliberately route the TBD label through a computed ref so the template
// never carries a hardcoded English literal (NoHardcodedStringsTest).
const labelA = computed<string>(() => props.node.participant_a?.clan_name ?? '—');
const labelB = computed<string>(() => props.node.participant_b?.clan_name ?? '—');

const isWinnerA = computed<boolean>(
    () =>
        props.node.winner_participant_id !== null &&
        props.node.participant_a !== null &&
        props.node.winner_participant_id === props.node.participant_a.id,
);
const isWinnerB = computed<boolean>(
    () =>
        props.node.winner_participant_id !== null &&
        props.node.participant_b !== null &&
        props.node.winner_participant_id === props.node.participant_b.id,
);
</script>

<template>
    <g class="bracket-node">
        <rect
            :width="NODE_WIDTH"
            :height="NODE_HEIGHT"
            :fill="fillColor"
            stroke="var(--color-border)"
            stroke-width="1"
            rx="4"
        />
        <line
            x1="0"
            :y1="NODE_HEIGHT / 2"
            :x2="NODE_WIDTH"
            :y2="NODE_HEIGHT / 2"
            stroke="var(--color-border)"
            stroke-width="1"
        />
        <text
            x="10"
            y="20"
            font-size="12"
            :font-weight="isWinnerA ? '600' : '400'"
            fill="var(--color-text)"
        >{{ labelA }}</text>
        <text
            x="10"
            y="48"
            font-size="12"
            :font-weight="isWinnerB ? '600' : '400'"
            fill="var(--color-text)"
        >{{ labelB }}</text>
    </g>
</template>

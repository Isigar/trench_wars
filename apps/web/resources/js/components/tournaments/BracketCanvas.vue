<!-- Source: 06-12-PLAN.md Task 2 + 06-RESEARCH.md Pattern 8 (SVG bracket renderer)
     + Pitfall 9 (double-elim stage-group offset).
     Renders the bracket tree as SVG. Position math:
       x = (round_number - 1) * COLUMN_WIDTH + 20
       y = stageYOffset + (position - 1) * verticalSpacing + verticalSpacing/2
       verticalSpacing = ROW_HEIGHT * 2^(round_number - 1)
     Nodes are grouped by stage_type so double-elim renders winners-bracket /
     losers-bracket / grand-final in separate vertical bands without overlap
     (Pitfall 9 mitigation). -->
<script setup lang="ts">
import { NODE_HEIGHT, NODE_WIDTH } from '@/components/tournaments/bracket-node-dimensions';
import BracketNode from '@/components/tournaments/BracketNode.vue';
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type BracketNodeData = App.Data.BracketNodeData;
type BracketEdgeData = App.Data.BracketEdgeData;

const { t } = useT();

const COLUMN_WIDTH = 220;
const ROW_HEIGHT = 80;
const STAGE_GAP = 100;

const props = defineProps<{
    nodes: BracketNodeData[];
    edges: BracketEdgeData[];
}>();

// Empty-state flag — computed in <script> to keep template free of `>` comparisons
// that confuse NoHardcodedStringsTest's regex.
const hasNodes = computed<boolean>(() => props.nodes.length >= 1);

// Group nodes by stage_type so each stage gets its own y-band (Pitfall 9).
const nodesByStage = computed<Map<string, BracketNodeData[]>>(() => {
    const map = new Map<string, BracketNodeData[]>();
    for (const n of props.nodes) {
        const arr = map.get(n.stage_type);
        if (arr) {
            arr.push(n);
        } else {
            map.set(n.stage_type, [n]);
        }
    }
    return map;
});

// Compute (x, y) per node, keyed by node.id.
const positions = computed<Map<string, { x: number; y: number }>>(() => {
    const map = new Map<string, { x: number; y: number }>();
    let stageYOffset = 0;

    for (const [, stageNodes] of nodesByStage.value) {
        if (stageNodes.length === 0) continue;
        const maxRound = Math.max(...stageNodes.map((n) => n.round_number));
        const round1Count = stageNodes.filter((n) => n.round_number === 1).length;

        for (const n of stageNodes) {
            const x = (n.round_number - 1) * COLUMN_WIDTH + 20;
            const verticalSpacing = ROW_HEIGHT * Math.pow(2, n.round_number - 1);
            const y = stageYOffset + (n.position - 1) * verticalSpacing + verticalSpacing / 2;
            map.set(n.id, { x, y });
        }

        // Reserve y-band for the next stage group.
        const stageHeight = Math.max(round1Count, 1) * ROW_HEIGHT * Math.pow(2, maxRound - 1);
        stageYOffset += stageHeight + STAGE_GAP;
    }

    return map;
});

const viewBox = computed<string>(() => {
    let maxX = 0;
    let maxY = 0;
    positions.value.forEach((p) => {
        if (p.x + NODE_WIDTH > maxX) maxX = p.x + NODE_WIDTH;
        if (p.y + NODE_HEIGHT > maxY) maxY = p.y + NODE_HEIGHT;
    });
    return `0 0 ${maxX + 40} ${maxY + 40}`;
});

function edgeStartX(fromId: string): number {
    return (positions.value.get(fromId)?.x ?? 0) + NODE_WIDTH;
}
function edgeStartY(fromId: string): number {
    return (positions.value.get(fromId)?.y ?? 0) + NODE_HEIGHT / 2;
}
function edgeEndX(toId: string): number {
    return positions.value.get(toId)?.x ?? 0;
}
function edgeEndY(toId: string): number {
    return (positions.value.get(toId)?.y ?? 0) + NODE_HEIGHT / 2;
}
function edgeStroke(type: string): string {
    return type === 'loser'
        ? 'var(--color-bracket-loser-line)'
        : 'var(--color-bracket-winner-line)';
}
function nodeTransform(id: string): string {
    const p = positions.value.get(id);
    return p ? `translate(${p.x}, ${p.y})` : 'translate(0, 0)';
}
</script>

<template>
    <div class="bracket-canvas">
        <svg
            v-if="hasNodes"
            :viewBox="viewBox"
            class="w-full h-auto"
            role="img"
            :aria-label="t('tournaments.tabs.bracket.label')"
        >
            <g class="edges">
                <line
                    v-for="edge in edges"
                    :key="`${edge.from_bracket_id}-${edge.to_bracket_id}-${edge.type}`"
                    :x1="edgeStartX(edge.from_bracket_id)"
                    :y1="edgeStartY(edge.from_bracket_id)"
                    :x2="edgeEndX(edge.to_bracket_id)"
                    :y2="edgeEndY(edge.to_bracket_id)"
                    :stroke="edgeStroke(edge.type)"
                    stroke-width="2"
                />
            </g>
            <g class="nodes">
                <g
                    v-for="node in nodes"
                    :key="node.id"
                    :transform="nodeTransform(node.id)"
                >
                    <BracketNode :node="node" />
                </g>
            </g>
        </svg>
        <div v-else role="status" class="py-12 text-center">
            <p class="text-base text-[var(--color-text-muted)]">
                {{ t('tournaments.show.bracket_empty') }}
            </p>
        </div>
    </div>
</template>

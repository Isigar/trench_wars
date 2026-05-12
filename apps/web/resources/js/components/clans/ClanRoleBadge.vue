<!-- Source: 02-UI-SPEC.md § Component Inventory + § Color (leader accent border) + § Typography (mono 14px/600). -->
<script setup lang="ts">
import { computed } from 'vue';
import { useT } from '@/composables/useT';

const { t } = useT();

type Role = 'leader' | 'officer' | 'member' | 'recruit';

const props = defineProps<{
    role: Role;
}>();

// UI-SPEC: Leader gets accent border + color-text; others get surface-elevated + text-muted.
const roleClasses = computed(() => {
    if (props.role === 'leader') {
        return 'bg-[var(--color-surface-elevated)] text-[var(--color-text)] border border-[var(--color-accent)]';
    }
    return 'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]';
});
</script>

<template>
    <!-- UI-SPEC Typography: Label size (14px/text-sm), font-mono, weight 600, px-2 py-1, rounded-sm -->
    <span
        :class="[
            'inline-flex items-center px-2 py-1 rounded-sm',
            'font-mono text-sm font-semibold',
            roleClasses,
        ]"
    >{{ t(`common.role.${role}`) }}</span>
</template>

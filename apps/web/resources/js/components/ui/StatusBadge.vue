<!-- Source: 02-UI-SPEC.md § Component Inventory + § Color § Status pill color assignments. -->
<script setup lang="ts">
import { computed } from 'vue';

// Source: 02-UI-SPEC.md § Color § Status pill color assignments.
type Variant =
    | 'active'
    | 'suspended'
    | 'disbanded'
    | 'pending'
    | 'public'
    | 'community'
    | 'clan'
    | 'private'
    | 'leader'
    | 'officer'
    | 'member'
    | 'recruit';

const props = withDefaults(
    defineProps<{
        variant: Variant;
        label?: string;
    }>(),
    {
        label: undefined,
    },
);

// Variants that use color-mix() for opacity backgrounds must be applied via inline
// style (Tailwind v4 + CSS variables limitation — see UI-SPEC implementation note).
const needsInlineStyle = computed(() =>
    ['pending', 'public', 'private'].includes(props.variant),
);

// Static class-based variants for tokens that don't need opacity mixing.
const staticClass = computed(() => {
    const map: Record<string, string> = {
        active:    'bg-[var(--color-success)] text-[var(--color-accent-fg)]',
        suspended: 'bg-[var(--color-warning)] text-[var(--color-accent-fg)]',
        disbanded: 'bg-[var(--color-danger)] text-[var(--color-accent-fg)]',
        community: 'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]',
        clan:      'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]',
        leader:    'bg-[var(--color-surface-elevated)] text-[var(--color-text)] border border-[var(--color-accent)]',
        officer:   'bg-[var(--color-surface-elevated)] text-[var(--color-text)]',
        member:    'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]',
        recruit:   'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]',
        // pending/public/private handled via inlineStyle
        pending: '',
        public:  '',
        private: '',
    };
    return map[props.variant] ?? '';
});

// Inline style for opacity-background variants.
// UI-SPEC: "use color-mix(in srgb, var(--color-warning) 20%, transparent)"
const inlineStyle = computed(() => {
    if (!needsInlineStyle.value) return {};
    const colorMap: Record<string, string> = {
        pending: 'color-mix(in srgb, var(--color-warning) 20%, transparent)',
        public:  'color-mix(in srgb, var(--color-success) 20%, transparent)',
        private: 'color-mix(in srgb, var(--color-danger) 20%, transparent)',
    };
    const textMap: Record<string, string> = {
        pending: 'var(--color-warning)',
        public:  'var(--color-success)',
        private: 'var(--color-danger)',
    };
    return {
        backgroundColor: colorMap[props.variant],
        color: textMap[props.variant],
    };
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center px-2 py-0.5 rounded-sm text-sm font-semibold',
            staticClass,
        ]"
        :style="inlineStyle"
    >{{ label ?? variant }}</span>
</template>

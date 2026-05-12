<!-- Source: 02-UI-SPEC.md § Component Inventory + § Color Accent reserved list (tag badge border). -->
<script setup lang="ts">
// ClanTagData shape as generated in resources/js/types/api.d.ts
export interface ClanTagData {
    id: string;
    slug: string;
    label: Record<string, string> | null;
    color: string | null;
}

withDefaults(
    defineProps<{
        tag: ClanTagData;
        /** Selected state used by filter bar (renders as button when as=button). */
        selected?: boolean;
        /**
         * Render as a button (for filter-bar use) or a span (display only).
         * A11y: filter pills are buttons with aria-pressed (UI-SPEC Accessibility Contract).
         */
        as?: 'span' | 'button';
    }>(),
    {
        selected: false,
        as: 'span',
    },
);

// Resolve the tag label: prefer 'en' locale, fallback to slug.
function resolveLabel(tag: ClanTagData): string {
    if (!tag.label) return tag.slug;
    return tag.label['en'] ?? Object.values(tag.label)[0] ?? tag.slug;
}
</script>

<template>
    <component
        :is="as"
        :aria-pressed="as === 'button' ? selected : undefined"
        :class="[
            'inline-flex items-center px-2 py-0.5 rounded-sm',
            'font-mono text-sm font-semibold',
            'border transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
            selected
                ? 'border-[var(--color-accent)] text-[var(--color-text)]'
                : 'border-[var(--color-border)] text-[var(--color-text-muted)]',
            as === 'button' ? 'cursor-pointer hover:border-[var(--color-accent)] focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]' : '',
        ]"
    >
        {{ resolveLabel(tag) }}
    </component>
</template>

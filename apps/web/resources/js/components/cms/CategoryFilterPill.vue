<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 33 (CategoryFilterPill.vue —
     active state via Inertia router param; clicking re-navigates with ?category=slug). -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    /** Category slug; `null` represents the "All" reset pill. */
    slug: string | null;
    /** Display label (already locale-resolved in the controller). */
    label: string;
    /** True when the current ?category= matches this pill. */
    active: boolean;
}>();

// The reset pill drops the ?category= param entirely; the slug-bearing pills
// build their href via a relative path so the Inertia router preserves the
// rest of the page state.
const href = computed<string>(() => (props.slug === null ? '/blog' : `/blog?category=${props.slug}`));
</script>

<template>
    <Link
        :href="href"
        :data-active="active ? 'true' : 'false'"
        :aria-current="active ? 'page' : undefined"
        :class="[
            'inline-block px-3 py-1 rounded-full text-xs font-semibold',
            'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
            'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
            active
                ? 'bg-[var(--color-accent)] text-[var(--color-accent-fg)]'
                : 'bg-[var(--color-surface)] text-[var(--color-text-muted)] border border-[var(--color-border)] hover:text-[var(--color-text)]',
        ]"
    >
        {{ label }}
    </Link>
</template>

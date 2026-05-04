<!-- Source: 01-UI-SPEC.md § Spacing Scale "Touch targets: minimum hit-area 44×44px on mobile". -->
<script setup lang="ts">
defineProps<{
    label: string; // accessible label for screen readers
    type?: 'button' | 'submit';
}>();
</script>

<template>
    <button
        :type="type ?? 'button'"
        :aria-label="label"
        class="inline-flex items-center justify-center
               h-11 w-11 md:h-9 md:w-9
               rounded-md text-[var(--color-text-muted)]
               hover:bg-[var(--color-surface)] hover:text-[var(--color-text)]
               transition-[background-color,color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]"
    >
        <slot />
    </button>
</template>

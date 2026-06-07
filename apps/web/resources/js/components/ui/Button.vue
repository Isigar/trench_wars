<!-- Source: 01-UI-SPEC.md § Components delivered in P1; § Color: Accent reserved for. -->
<script setup lang="ts">
import { computed } from 'vue';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        size?: Size;
        type?: 'button' | 'submit' | 'reset';
        disabled?: boolean;
    }>(),
    {
        variant: 'secondary',
        size: 'md',
        type: 'button',
        disabled: false,
    },
);

const variantClasses = computed(() => ({
    primary: 'bg-[var(--color-accent)] text-[var(--color-accent-fg)] hover:opacity-90',
    secondary: 'bg-[var(--color-surface)] text-[var(--color-text)] border border-[var(--color-border)] hover:bg-[var(--color-surface-elevated)]',
    ghost: 'bg-transparent text-[var(--color-text)] hover:bg-[var(--color-surface)]',
    danger: 'bg-[var(--color-danger)] text-white hover:opacity-90',
})[props.variant]);

const sizeClasses = computed(() => ({
    sm: 'h-8 px-3 text-sm',
    md: 'h-10 px-4 text-sm',
    lg: 'h-12 px-6 text-base',
})[props.size]);
</script>

<template>
    <button
        :type="type"
        :disabled="disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 font-semibold rounded-md',
            'transition-[background-color,opacity,box-shadow] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
            'disabled:opacity-50 disabled:cursor-not-allowed',
            variantClasses,
            sizeClasses,
        ]"
    >
        <slot />
    </button>
</template>

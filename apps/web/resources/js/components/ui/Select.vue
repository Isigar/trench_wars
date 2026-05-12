<!-- Source: 02-UI-SPEC.md § Component Inventory — "use native for P2 (simpler, fully accessible)". -->
<script setup lang="ts">
import { computed } from 'vue';

export interface SelectOption {
    value: string;
    label: string;
}

const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        /** Already-translated label text (parent calls t() before passing). */
        label: string;
        id: string;
        options: SelectOption[];
        required?: boolean;
        /** Validation error strings — first error is displayed. */
        errors?: string[];
    }>(),
    {
        required: false,
        errors: () => [],
    },
);

const hasError = computed(() => props.errors && props.errors.length > 0);
const errorId = computed(() => `${props.id}-error`);
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="text-sm font-semibold text-[var(--color-text)]">
            {{ label }}<span v-if="required" aria-hidden="true" class="text-[var(--color-danger)] ml-0.5">*</span>
        </label>

        <select
            :id="id"
            v-model="model"
            :required="required"
            :aria-describedby="hasError ? errorId : undefined"
            :aria-invalid="hasError ? 'true' : undefined"
            class="h-10 px-3 w-full rounded-md text-sm text-[var(--color-text)] bg-[var(--color-surface)] border border-[var(--color-border)] focus:outline-2 focus:outline-[var(--color-focus-ring)] transition-[border-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <p v-if="hasError" :id="errorId" role="alert" class="text-sm text-[var(--color-danger)]">{{ errors![0] }}</p>
    </div>
</template>

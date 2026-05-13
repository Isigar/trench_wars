<!-- Source: 02-UI-SPEC.md § Component Inventory + § Page: /my-clan § Profile tab — form. -->
<script setup lang="ts">
import { computed } from 'vue';

const model = defineModel<string>({ default: '' });

const props = withDefaults(
    defineProps<{
        /** Already-translated label text (parent calls t() before passing). */
        label: string;
        id: string;
        type?: 'text' | 'email' | 'search' | 'date';
        placeholder?: string;
        required?: boolean;
        /** Validation error strings — first error is displayed. */
        errors?: string[];
    }>(),
    {
        type: 'text',
        placeholder: undefined,
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

        <input
            :id="id"
            v-model="model"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :aria-describedby="hasError ? errorId : undefined"
            :aria-invalid="hasError ? 'true' : undefined"
            class="h-10 px-3 w-full rounded-md text-sm text-[var(--color-text)] bg-[var(--color-surface)] border border-[var(--color-border)] placeholder:text-[var(--color-text-muted)] focus:outline-2 focus:outline-[var(--color-focus-ring)] transition-[border-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]"
        />

        <p v-if="hasError" :id="errorId" role="alert" class="text-sm text-[var(--color-danger)]">{{ errors![0] }}</p>
    </div>
</template>

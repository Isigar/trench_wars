<!-- Source: 02-UI-SPEC.md § Component Inventory + § Modal: Invite member + T-02-06-03 (focus trap). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import IconButton from '@/components/ui/IconButton.vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { X } from 'lucide-vue-next';

const { t } = useT();

defineProps<{
    /** Whether the dialog is open. Bind with v-model:open. */
    open: boolean;
    /** Already-translated title string (parent must call t() before passing). */
    title: string;
    /** Optional accessible description for screen readers. */
    description?: string;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <DialogRoot
        :open="open"
        @update:open="emit('update:open', $event)"
    >
        <DialogPortal>
            <!-- Backdrop — bg-black/60 per UI-SPEC -->
            <DialogOverlay
                class="fixed inset-0 z-40 bg-black/60
                       data-[state=open]:animate-in data-[state=closed]:animate-out
                       data-[state=open]:fade-in data-[state=closed]:fade-out
                       duration-[var(--motion-duration-base)]"
            />

            <!-- Dialog panel — max-w-lg, bg surface-elevated, rounded-lg, p-6 -->
            <!-- Focus trap is built into Reka UI DialogContent (T-02-06-03 — do NOT override inert/aria-modal). -->
            <DialogContent
                class="fixed left-1/2 top-1/2 z-50 w-full max-w-lg -translate-x-1/2 -translate-y-1/2
                       bg-[var(--color-surface-elevated)] rounded-lg p-6 shadow-xl
                       data-[state=open]:animate-in data-[state=closed]:animate-out
                       data-[state=open]:fade-in data-[state=closed]:fade-out
                       data-[state=open]:zoom-in-95 data-[state=closed]:zoom-out-95
                       duration-[var(--motion-duration-base)]
                       focus:outline-none"
            >
                <!-- Header row: title + close button -->
                <div class="flex items-center justify-between gap-4 mb-6">
                    <DialogTitle class="text-xl font-semibold text-[var(--color-text)]">
                        {{ title }}
                    </DialogTitle>

                    <DialogClose as-child>
                        <IconButton :label="t('common.actions.close')">
                            <X :size="16" aria-hidden="true" />
                        </IconButton>
                    </DialogClose>
                </div>

                <!-- Optional SR description -->
                <DialogDescription v-if="description" class="sr-only">
                    {{ description }}
                </DialogDescription>

                <!-- Content slot -->
                <slot />
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>

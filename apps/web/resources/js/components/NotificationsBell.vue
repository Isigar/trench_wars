<!-- Source: .planning/phases/09-polish/09-06-PLAN.md task 1 + 09-RESEARCH.md
     § Bell UX.

     Bell button in PublicLayout (auth-only). Renders a count badge sourced from
     the Inertia shared prop `unread_notifications_count` (HandleInertiaRequests
     middleware) so the badge stays fresh across every Inertia navigation with
     no extra round-trip (SC-1 — no real-time polling).

     The dropdown content is a lightweight summary; the full list lives on
     /notifications. We use Reka Popover (not Drawer — reka-ui ships Popover
     + Dialog, not a separate Drawer primitive). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Bell } from 'lucide-vue-next';
import { Link, router } from '@inertiajs/vue3';
import {
    PopoverArrow,
    PopoverClose,
    PopoverContent,
    PopoverPortal,
    PopoverRoot,
    PopoverTrigger,
} from 'reka-ui';
import { computed } from 'vue';

const { t } = useT();

const props = withDefaults(
    defineProps<{
        count: number;
    }>(),
    {
        count: 0,
    },
);

const hasUnread = computed<boolean>(() => props.count > 0);
const ariaLabel = computed<string>(() => t('a11y.notifications.bell_label', { count: props.count }));

function markAllRead(): void {
    router.post(
        route('notifications.markAllRead'),
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <PopoverRoot>
        <PopoverTrigger
            :aria-label="ariaLabel"
            class="relative inline-flex items-center justify-center h-9 w-9 rounded-md
                   text-[var(--color-text)]
                   hover:bg-[var(--color-surface-elevated)]
                   transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
        >
            <Bell :size="18" aria-hidden="true" />

            <!-- Badge — only when count > 0. Semantic accent token, not raw hex. -->
            <span
                v-if="hasUnread"
                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full
                       inline-flex items-center justify-center
                       text-[10px] font-semibold leading-none
                       bg-[var(--color-accent)] text-[var(--color-accent-fg)]"
                aria-hidden="true"
            >{{ count }}</span>
        </PopoverTrigger>

        <PopoverPortal>
            <PopoverContent
                :side-offset="6"
                align="end"
                class="z-50 w-80 max-w-[calc(100vw-2rem)]
                       rounded-md p-3
                       bg-[var(--color-surface-elevated)] border border-[var(--color-border)]
                       shadow-lg
                       data-[state=open]:animate-in data-[state=closed]:animate-out
                       data-[state=open]:fade-in data-[state=closed]:fade-out
                       duration-[var(--motion-duration-fast)]"
            >
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h2 class="text-sm font-semibold text-[var(--color-text)]">
                        {{ t('notifications.bell.unread_count', { count: props.count }) }}
                    </h2>

                    <button
                        v-if="hasUnread"
                        type="button"
                        :aria-label="t('a11y.notifications.mark_all_read')"
                        class="text-xs font-semibold text-[var(--color-accent)] hover:underline
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        @click="markAllRead"
                    >
                        {{ t('notifications.cta.mark_all_read') }}
                    </button>
                </div>

                <div v-if="!hasUnread" class="py-4 text-sm text-[var(--color-text-muted)]">
                    {{ t('notifications.bell.empty_state') }}
                </div>

                <div class="mt-2">
                    <PopoverClose as-child>
                        <Link
                            href="/notifications"
                            class="block text-sm font-semibold text-[var(--color-text)]
                                   hover:text-[var(--color-accent)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        >
                            {{ t('notifications.bell.view_all') }}
                        </Link>
                    </PopoverClose>
                </div>

                <PopoverArrow class="fill-[var(--color-surface-elevated)]" />
            </PopoverContent>
        </PopoverPortal>
    </PopoverRoot>
</template>

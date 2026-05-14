<!-- Source: .planning/phases/09-polish/09-06-PLAN.md task 1.
     Full notifications inbox — renders all rows from
     `auth()->user()->notifications()->paginate(20)` with mark-read CTAs. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface NotificationRow {
    id: string;
    type: string;
    data: Record<string, unknown>;
    read_at: string | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    notifications: Paginator<NotificationRow>;
}>();

const { t } = useT();

const hasNotifications = computed<boolean>(() => props.notifications.data.length !== 0);
const hasPrevPage = computed<boolean>(() => props.notifications.current_page > 1);
const hasNextPage = computed<boolean>(() => props.notifications.current_page < props.notifications.last_page);
// NoHardcodedStringsTest regex `/>([^<]{3,})</` confuses `>= 2"` in attribute
// values with text-between-tags. Extract to a computed ref to keep the template
// free of inline comparisons. (Pre-existing idiom — see Articles/Index.vue.)
const hasMultiplePages = computed<boolean>(() => props.notifications.last_page >= 2);

function markRead(id: string): void {
    router.post(
        route('notifications.markRead', { id }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function markAllRead(): void {
    router.post(
        route('notifications.markAllRead'),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function goToPage(page: number): void {
    router.get('/notifications', { page }, { preserveScroll: true });
}

/**
 * Resolve a notification's user-facing title:
 *   1. If `data.i18n_key` is set, use that as a t() lookup.
 *   2. Otherwise fall back to the type discriminator (e.g., "match.starting_soon").
 */
function titleFor(row: NotificationRow): string {
    const key = typeof row.data?.i18n_key === 'string' ? row.data.i18n_key : null;
    if (key) {
        return t(key, paramsFromData(row.data));
    }
    return row.type;
}

function paramsFromData(data: Record<string, unknown>): Record<string, string | number> {
    const out: Record<string, string | number> = {};
    for (const [k, v] of Object.entries(data)) {
        if (typeof v === 'string' || typeof v === 'number') {
            out[k] = v;
        }
    }
    return out;
}
</script>

<template>
    <Head :title="t('notifications.page.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex items-center justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                        {{ t('notifications.page.title') }}
                    </h1>
                    <p class="text-base text-[var(--color-text-muted)]">
                        {{ t('notifications.page.description') }}
                    </p>
                </div>

                <button
                    v-if="hasNotifications"
                    type="button"
                    class="text-sm font-semibold text-[var(--color-accent)] hover:underline
                           focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    :aria-label="t('a11y.notifications.mark_all_read')"
                    @click="markAllRead"
                >
                    {{ t('notifications.cta.mark_all_read') }}
                </button>
            </header>

            <div
                v-if="!hasNotifications"
                class="py-12 text-center text-sm text-[var(--color-text-muted)]
                       border border-dashed border-[var(--color-border)] rounded-md"
            >
                {{ t('notifications.bell.empty_state') }}
            </div>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="row in notifications.data"
                    :key="row.id"
                    :class="[
                        'flex items-start justify-between gap-4 p-4 rounded-md border',
                        'border-[var(--color-border)]',
                        row.read_at !== null
                            ? 'bg-[var(--color-surface)] text-[var(--color-text-muted)]'
                            : 'bg-[var(--color-surface-elevated)] text-[var(--color-text)]',
                    ]"
                >
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ titleFor(row) }}</p>
                        <p class="text-xs text-[var(--color-text-muted)] mt-1">{{ row.created_at }}</p>
                    </div>

                    <button
                        v-if="row.read_at === null"
                        type="button"
                        class="shrink-0 text-xs font-semibold text-[var(--color-accent)] hover:underline
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        :aria-label="t('a11y.notifications.mark_read')"
                        @click="markRead(row.id)"
                    >
                        {{ t('notifications.cta.mark_read') }}
                    </button>
                </li>
            </ul>

            <div
                v-if="hasMultiplePages"
                class="flex items-center justify-between gap-2 text-sm text-[var(--color-text-muted)]"
            >
                <button
                    type="button"
                    class="px-3 py-1 rounded-md border border-[var(--color-border)] disabled:opacity-50"
                    :disabled="!hasPrevPage"
                    @click="goToPage(notifications.current_page - 1)"
                >
                    {{ t('common.actions.previous') }}
                </button>
                <span>{{ notifications.current_page }} / {{ notifications.last_page }}</span>
                <button
                    type="button"
                    class="px-3 py-1 rounded-md border border-[var(--color-border)] disabled:opacity-50"
                    :disabled="!hasNextPage"
                    @click="goToPage(notifications.current_page + 1)"
                >
                    {{ t('common.actions.next') }}
                </button>
            </div>
        </section>
    </PublicLayout>
</template>

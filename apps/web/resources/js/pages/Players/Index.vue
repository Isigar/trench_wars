<!-- Public player directory index (/players). Mirrors Clans/Index: a name-search
     box + a responsive grid of player cards + Prev/Next pagination. Privacy-tier
     filtering was applied server-side (PlayerPrivacyGate) — the Vue layer never
     re-derives privacy. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { computed, ref } from 'vue';

const { t } = useT();

type PlayerSummaryData = App.Data.PlayerSummaryData;

interface Pagination {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}

const props = defineProps<{
    players: PlayerSummaryData[];
    pagination: Pagination;
    activeSearch?: string;
}>();

const searchInput = ref<string>(props.activeSearch ?? '');

// Booleans kept in script so the template carries no `>`/`<` comparison literals
// (NoHardcodedStringsTest regex misreads them as text-node boundaries).
const hasPrev = computed<boolean>(() => props.pagination.currentPage > 1);
const hasNext = computed<boolean>(() => props.pagination.currentPage < props.pagination.lastPage);
const isEmpty = computed<boolean>(() => props.players.length === 0);

function applySearch(): void {
    router.get('/players', { q: searchInput.value }, { preserveScroll: true, replace: true });
}

function goToPage(page: number): void {
    router.get('/players', { q: props.activeSearch ?? '', page }, { preserveScroll: true });
}

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0] ?? '')
        .join('')
        .toUpperCase();
}
</script>

<template>
    <Head :title="t('players.index.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('players.index.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('players.index.subtitle') }}
                </p>
            </header>

            <form class="flex gap-2" @submit.prevent="applySearch">
                <div class="flex-1">
                    <TextInput
                        id="players-search"
                        v-model="searchInput"
                        :label="t('players.index.search_label')"
                        :placeholder="t('players.index.search_placeholder')"
                    />
                </div>
            </form>

            <div
                v-if="isEmpty"
                role="status"
                class="py-12 text-center text-base text-[var(--color-text-muted)]"
            >
                {{ t('players.index.empty') }}
            </div>

            <ul v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <li v-for="player in players" :key="player.id">
                    <a
                        :href="`/players/${player.slug}`"
                        class="flex items-center gap-3 p-3 rounded-lg
                               border border-[var(--color-border)] bg-[var(--color-surface-elevated)]
                               hover:bg-[var(--color-surface)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]
                               transition-colors duration-[var(--motion-duration-fast)]"
                    >
                        <span
                            class="w-12 h-12 rounded-full shrink-0 flex items-center justify-center
                                   bg-[var(--color-surface)] text-[var(--color-text-muted)]
                                   text-base font-semibold select-none"
                            aria-hidden="true"
                        >
                            {{ initials(player.displayName) }}
                        </span>
                        <span class="flex flex-col min-w-0">
                            <span class="font-semibold text-[var(--color-text)] truncate">
                                {{ player.displayName }}
                            </span>
                            <span
                                v-if="player.countryCode"
                                class="text-xs text-[var(--color-text-muted)]"
                            >
                                {{ player.countryCode }}
                            </span>
                        </span>
                    </a>
                </li>
            </ul>

            <!-- Pagination -->
            <div v-if="!isEmpty" class="flex items-center justify-between pt-2">
                <button
                    type="button"
                    :disabled="!hasPrev"
                    class="inline-flex items-center h-10 px-4 text-sm font-semibold rounded-md
                           bg-[var(--color-surface)] text-[var(--color-text)]
                           border border-[var(--color-border)]
                           disabled:opacity-50 disabled:cursor-not-allowed
                           hover:bg-[var(--color-surface-elevated)]
                           focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    @click="goToPage(pagination.currentPage - 1)"
                >
                    {{ t('players.index.pagination_prev') }}
                </button>

                <span class="text-sm text-[var(--color-text-muted)]">
                    {{ t('players.index.pagination_page_indicator', { current: pagination.currentPage, last: pagination.lastPage }) }}
                </span>

                <button
                    type="button"
                    :disabled="!hasNext"
                    class="inline-flex items-center h-10 px-4 text-sm font-semibold rounded-md
                           bg-[var(--color-surface)] text-[var(--color-text)]
                           border border-[var(--color-border)]
                           disabled:opacity-50 disabled:cursor-not-allowed
                           hover:bg-[var(--color-surface-elevated)]
                           focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    @click="goToPage(pagination.currentPage + 1)"
                >
                    {{ t('players.index.pagination_next') }}
                </button>
            </div>
        </section>
    </PublicLayout>
</template>

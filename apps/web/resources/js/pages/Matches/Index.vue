<!-- Source: 04-11-PLAN.md Task 1 + 04-RESEARCH.md § Pattern 7 (calendar page). -->
<script setup lang="ts">
import MatchCard from '@/components/matches/MatchCard.vue';
import Select, { type SelectOption } from '@/components/ui/Select.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useT } from '@/composables/useT';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { computed, ref } from 'vue';

const { t } = useT();

type PublicMatchData = App.Data.PublicMatchData;

// MatchCalendarController passes its paginator items through PublicMatchData::fromModel.
// The eager-loaded `slots` collection arrives alongside the DTO shape so MatchCard can
// compute the "X / Y signed up" summary; we type-overlay it permissively.
type CalendarMatchEntry = PublicMatchData & {
    slots?: Array<{ occupant_user_id?: string | null }>;
};

interface Pagination {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}

interface ActiveFilters {
    dateFrom: string | null;
    dateTo: string | null;
    tag: string | null;
    status: string | null;
}

const props = defineProps<{
    matches: CalendarMatchEntry[];
    pagination: Pagination;
    activeFilters: ActiveFilters;
}>();

// ---------------------------------------------------------------------------
// Filter bar state — seeded from activeFilters; user edits + submits via Inertia GET.
// ---------------------------------------------------------------------------
const dateFromInput = ref<string>(props.activeFilters.dateFrom ?? '');
const dateToInput = ref<string>(props.activeFilters.dateTo ?? '');
const tagInput = ref<string>(props.activeFilters.tag ?? '');
const statusInput = ref<string>(props.activeFilters.status ?? '');

const statusOptions: SelectOption[] = [
    { value: '', label: t('matches.directory.filter_status_any') },
    { value: 'open', label: t('matches.status.label.open') },
    { value: 'locked', label: t('matches.status.label.locked') },
    { value: 'played', label: t('matches.status.label.played') },
];

function applyFilters(): void {
    const params: Record<string, string> = {};
    if (dateFromInput.value) params.date_from = dateFromInput.value;
    if (dateToInput.value) params.date_to = dateToInput.value;
    if (tagInput.value) params.tag = tagInput.value;
    if (statusInput.value) params.status = statusInput.value;
    router.get('/matches', params, { preserveScroll: true, replace: true });
}

function clearFilters(): void {
    dateFromInput.value = '';
    dateToInput.value = '';
    tagInput.value = '';
    statusInput.value = '';
    router.get('/matches', {}, { preserveScroll: true, replace: true });
}

function hasActiveFilters(): boolean {
    const af = props.activeFilters;
    // dateFrom is auto-defaulted to today by the controller — we only treat it as "active"
    // when the user explicitly set it (i.e., it's present in their submitted URL).
    return !!(af.dateTo || af.tag || af.status);
}

// Pagination flags — computed in <script> to keep template free of `>` comparison
// operators (NoHardcodedStringsTest's regex misreads `>` in attribute values as
// the start of a text node).
const hasMultiplePages = computed<boolean>(() => props.pagination.lastPage >= 2);
const isOnFirstPage = computed<boolean>(() => props.pagination.currentPage <= 1);
const isOnLastPage = computed<boolean>(
    () => props.pagination.currentPage >= props.pagination.lastPage,
);

function goToPage(page: number): void {
    if (page < 1 || page > props.pagination.lastPage) return;
    const params: Record<string, string | number> = { page };
    if (dateFromInput.value) params.date_from = dateFromInput.value;
    if (dateToInput.value) params.date_to = dateToInput.value;
    if (tagInput.value) params.tag = tagInput.value;
    if (statusInput.value) params.status = statusInput.value;
    router.get('/matches', params, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('matches.directory.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8">
            <div class="flex flex-col gap-4">
                <!-- Page heading -->
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('matches.directory.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('matches.directory.subtitle') }}
                </p>

                <!-- Filter bar -->
                <div class="flex flex-col gap-3 mt-2">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <TextInput
                                id="matches-date-from"
                                v-model="dateFromInput"
                                :label="t('matches.directory.filter_date_from_label')"
                                type="date"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="flex-1">
                            <TextInput
                                id="matches-date-to"
                                v-model="dateToInput"
                                :label="t('matches.directory.filter_date_to_label')"
                                type="date"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <TextInput
                                id="matches-tag"
                                v-model="tagInput"
                                :label="t('matches.directory.filter_tag_label')"
                                type="text"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <div class="flex-1">
                            <Select
                                id="matches-status"
                                v-model="statusInput"
                                :label="t('matches.directory.filter_status_label')"
                                :options="statusOptions"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center h-10 px-4 rounded-md
                                   bg-[var(--color-accent)] text-[var(--color-accent-fg)]
                                   text-sm font-semibold hover:opacity-90
                                   transition-[background-color,opacity] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                            @click="applyFilters"
                        >
                            {{ t('matches.directory.filter_status_label') }}
                        </button>
                        <button
                            v-if="hasActiveFilters()"
                            type="button"
                            class="self-start text-sm font-semibold text-[var(--color-text-muted)]
                                   hover:text-[var(--color-text)] transition-colors
                                   duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                            @click="clearFilters"
                        >
                            {{ t('matches.directory.filter_clear') }}
                        </button>
                    </div>
                </div>

                <!-- Match list OR empty states -->
                <div class="mt-2">
                    <div
                        v-if="matches.length === 0 && hasActiveFilters()"
                        role="status"
                        class="py-12 text-center"
                    >
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('matches.directory.empty_results') }}
                        </p>
                        <button
                            type="button"
                            class="mt-4 text-sm font-semibold text-[var(--color-text-muted)]
                                   hover:text-[var(--color-text)] underline
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                            @click="clearFilters"
                        >
                            {{ t('matches.directory.filter_clear') }}
                        </button>
                    </div>

                    <div
                        v-else-if="matches.length === 0"
                        role="status"
                        class="py-12 text-center"
                    >
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('matches.directory.empty_default') }}
                        </p>
                    </div>

                    <div
                        v-else
                        class="flex flex-col gap-3"
                    >
                        <MatchCard
                            v-for="match in matches"
                            :key="match.id"
                            :match="match"
                        />
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    v-if="hasMultiplePages"
                    class="flex items-center justify-between gap-3 mt-4"
                >
                    <button
                        type="button"
                        :disabled="isOnFirstPage"
                        class="inline-flex items-center justify-center h-8 px-3 rounded-md
                               bg-[var(--color-surface)] text-[var(--color-text)] border border-[var(--color-border)]
                               text-sm font-semibold
                               hover:bg-[var(--color-surface-elevated)]
                               disabled:opacity-50 disabled:cursor-not-allowed
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        @click="goToPage(pagination.currentPage - 1)"
                    >
                        {{ t('matches.directory.pagination_prev') }}
                    </button>
                    <span class="text-sm text-[var(--color-text-muted)]">
                        {{ t('matches.directory.pagination_page_indicator', { current: pagination.currentPage, last: pagination.lastPage }) }}
                    </span>
                    <button
                        type="button"
                        :disabled="isOnLastPage"
                        class="inline-flex items-center justify-center h-8 px-3 rounded-md
                               bg-[var(--color-surface)] text-[var(--color-text)] border border-[var(--color-border)]
                               text-sm font-semibold
                               hover:bg-[var(--color-surface-elevated)]
                               disabled:opacity-50 disabled:cursor-not-allowed
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        @click="goToPage(pagination.currentPage + 1)"
                    >
                        {{ t('matches.directory.pagination_next') }}
                    </button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

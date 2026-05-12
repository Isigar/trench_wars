<!-- Source: 02-UI-SPEC.md § Page: /clans (Public clan directory). -->
<script setup lang="ts">
import ClanCard from '@/components/clans/ClanCard.vue';
import ClanTagBadge from '@/components/clans/ClanTagBadge.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { useT } from '@/composables/useT';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { ref } from 'vue';

const { t } = useT();

// Use the generated DTO types from api.d.ts
type ClanData = App.Data.ClanData;
type ClanTagData = App.Data.ClanTagData;

interface Pagination {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}

const props = defineProps<{
    clans: ClanData[];
    tags: ClanTagData[];
    pagination: Pagination;
    activeTagSlug?: string;
    activeSearch?: string;
}>();

// Local reactive state for the search input.
const searchInput = ref<string>(props.activeSearch ?? '');

// Navigate with filter params via Inertia.
function applyTag(slug: string | undefined): void {
    router.get(
        '/clans',
        { tag: slug ?? '', q: searchInput.value },
        { preserveScroll: true, replace: true },
    );
}

function applySearch(): void {
    router.get(
        '/clans',
        { tag: props.activeTagSlug ?? '', q: searchInput.value },
        { preserveScroll: true, replace: true },
    );
}

function clearFilters(): void {
    searchInput.value = '';
    router.get('/clans', {}, { preserveScroll: true, replace: true });
}

// Whether any filter is currently active.
function hasActiveFilters(): boolean {
    return !!(props.activeTagSlug || props.activeSearch);
}
</script>

<template>
    <Head :title="t('clans.directory.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8">
            <div class="flex flex-col gap-4">
                <!-- Page heading -->
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('clans.directory.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('clans.directory.subtitle') }}
                </p>

                <!-- Filter bar -->
                <div class="flex flex-col gap-3">
                    <!-- Search input — max-w-80 at md+, full width on mobile -->
                    <div class="w-full md:max-w-80">
                        <TextInput
                            id="clan-search"
                            v-model="searchInput"
                            :label="t('clans.directory.title')"
                            type="search"
                            :placeholder="t('clans.directory.search_placeholder')"
                            class="sr-only"
                            @keyup.enter="applySearch"
                        />
                    </div>

                    <!-- Tag filter pills — horizontal scroll row, no scrollbar -->
                    <div
                        v-if="tags.length"
                        class="flex items-center gap-2 overflow-x-auto pb-1"
                        role="group"
                        :aria-label="t('clans.filter.tag_label')"
                    >
                        <ClanTagBadge
                            v-for="tag in tags"
                            :key="tag.id"
                            :tag="tag"
                            :selected="activeTagSlug === tag.slug"
                            as="button"
                            @click="applyTag(activeTagSlug === tag.slug ? undefined : tag.slug)"
                        />
                    </div>

                    <!-- Clear filters link — shown only when any filter active -->
                    <button
                        v-if="hasActiveFilters()"
                        type="button"
                        class="self-start text-sm font-semibold text-[var(--color-text-muted)]
                               hover:text-[var(--color-text)] transition-colors
                               duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                        @click="clearFilters"
                    >
                        {{ t('clans.filter.clear') }}
                    </button>
                </div>

                <!-- Clan grid OR empty states -->
                <div class="mt-2">
                    <!-- Empty: filtered no results -->
                    <div
                        v-if="clans.length === 0 && hasActiveFilters()"
                        role="status"
                        class="py-12 text-center"
                    >
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('clans.directory.empty_results') }}
                        </p>
                        <button
                            type="button"
                            class="mt-4 text-sm font-semibold text-[var(--color-text-muted)]
                                   hover:text-[var(--color-text)] underline
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                            @click="clearFilters"
                        >
                            {{ t('clans.filter.clear') }}
                        </button>
                    </div>

                    <!-- Empty: no clans at all -->
                    <div
                        v-else-if="clans.length === 0"
                        role="status"
                        class="py-12 text-center"
                    >
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('clans.directory.empty_default') }}
                        </p>
                    </div>

                    <!-- Clan grid — responsive: 1 col mobile, 2 col sm+, 3 col lg+ -->
                    <div
                        v-else
                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6"
                    >
                        <ClanCard
                            v-for="clan in clans"
                            :key="clan.id"
                            :clan="clan"
                        />
                    </div>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

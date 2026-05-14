<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 30
     (Search/Results.vue — 3 sections: articles + clans + players with rank-sorted cards).

     The `query` prop is auto-escaped by Inertia's data-page attribute
     (htmlspecialchars(..., ENT_QUOTES) — T-07-09-06 mitigation proven in 07-09's
     SearchControllerTest). Echoing it here via {{ query }} stays safe because
     Vue's mustache interpolation calls textContent (NOT innerHTML). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type SearchResultsData = App.Data.SearchResultsData;
type SearchResultData = App.Data.SearchResultData;

interface PageMeta {
    title: string;
    description: string;
}

const props = defineProps<{
    results: SearchResultsData;
    query: string;
    meta: PageMeta;
}>();

const { t } = useT();

interface ResultSection {
    key: 'articles' | 'clans' | 'players';
    headingKey: string;
    rows: SearchResultData[];
}

const sections = computed<ResultSection[]>(() => [
    { key: 'articles', headingKey: 'search.results.section_articles', rows: props.results.articles },
    { key: 'clans', headingKey: 'search.results.section_clans', rows: props.results.clans },
    { key: 'players', headingKey: 'search.results.section_players', rows: props.results.players },
]);

const isEmpty = computed<boolean>(
    () =>
        props.results.articles.length === 0 &&
        props.results.clans.length === 0 &&
        props.results.players.length === 0,
);
</script>

<template>
    <Head :title="meta.title">
        <!-- Pitfall 4 mitigation: head-key dedupes across SPA navigation. -->
        <meta head-key="description" name="description" :content="meta.description" />
        <!-- T-07-12-08 mitigation: search-results pages MUST NOT be indexed
             (they leak query patterns + amplify thin-content penalties). -->
        <meta head-key="robots" name="robots" content="noindex" />
    </Head>

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6" data-test="search-results">
            <header class="flex flex-col gap-2">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('search.results.heading') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    <span>{{ t('search.placeholder.label') }}:</span>
                    <span class="font-semibold text-[var(--color-text)]">{{ query }}</span>
                </p>
            </header>

            <p
                v-if="isEmpty"
                class="text-base text-[var(--color-text-muted)] py-12 text-center"
                data-test="search-empty"
            >
                {{ t('search.results.empty_state') }}
            </p>

            <template v-else>
                <section
                    v-for="section in sections"
                    :key="section.key"
                    :data-test="`search-section-${section.key}`"
                    class="flex flex-col gap-3"
                >
                    <h2 class="text-lg font-semibold text-[var(--color-text)]">
                        {{ t(section.headingKey) }}
                    </h2>

                    <p
                        v-if="section.rows.length === 0"
                        class="text-sm text-[var(--color-text-muted)]"
                    >
                        {{ t('search.results.empty_state') }}
                    </p>

                    <ul v-else class="flex flex-col gap-2">
                        <li
                            v-for="row in section.rows"
                            :key="`${row.type}-${row.id}`"
                            class="flex items-center gap-3 p-3 rounded-md border border-[var(--color-border)] bg-[var(--color-surface)]"
                            :data-test="`search-row-${section.key}`"
                        >
                            <img
                                v-if="row.thumbnailUrl"
                                :src="row.thumbnailUrl"
                                :alt="row.title"
                                class="w-10 h-10 rounded-md object-cover"
                                loading="lazy"
                            />
                            <div class="flex-1 flex flex-col gap-1">
                                <Link
                                    :href="row.url"
                                    class="text-base font-semibold text-[var(--color-text)] hover:underline focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                                >
                                    {{ row.title }}
                                </Link>
                                <p v-if="row.excerpt" class="text-sm text-[var(--color-text-muted)] line-clamp-2">
                                    {{ row.excerpt }}
                                </p>
                            </div>
                            <span
                                class="px-2 py-1 rounded-full text-xs font-mono text-[var(--color-text-muted)] border border-[var(--color-border)]"
                                :title="String(row.rank)"
                            >
                                {{ row.rank.toFixed(2) }}
                            </span>
                        </li>
                    </ul>
                </section>
            </template>
        </section>
    </PublicLayout>
</template>

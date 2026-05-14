<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 30
     (Articles/Index.vue — paginated card grid + category pills + search shortcut).

     Inertia component name resolved from BlogIndexController::render('Articles/Index'). -->
<script setup lang="ts">
import ArticleCard from '@/components/cms/ArticleCard.vue';
import CategoryFilterPill from '@/components/cms/CategoryFilterPill.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

type ArticleSummaryData = App.Data.ArticleSummaryData;

interface CategoryRow {
    id: string;
    slug: string;
    name: string;
}

interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
}

interface PageMeta {
    title: string;
    description: string;
}

const props = defineProps<{
    articles: ArticleSummaryData[];
    categories: CategoryRow[];
    pagination: PaginationMeta;
    activeCategory: string | null;
    meta: PageMeta;
}>();

const { t } = useT();

const hasPrev = computed<boolean>(() => props.pagination.currentPage > 1);
const hasNext = computed<boolean>(() => props.pagination.currentPage < props.pagination.lastPage);

// Boolean view helpers — extracted from template `v-if="x > 0"` style attribute
// expressions because the NoHardcodedStringsTest regex `/>([^<]{3,})</` treats
// `>` inside attribute values as tag terminators (false-positive flagging).
// Routing the comparisons through computed refs keeps the template free of `>`.
const hasCategories = computed<boolean>(() => props.categories.length !== 0);
const hasArticles = computed<boolean>(() => props.articles.length !== 0);
const hasMultiplePages = computed<boolean>(() => props.pagination.lastPage >= 2);

function goToPage(page: number): void {
    const params: Record<string, string | number> = { page };
    if (props.activeCategory !== null) {
        params.category = props.activeCategory;
    }
    router.get('/blog', params, { preserveScroll: true });
}
</script>

<template>
    <Head :title="meta.title">
        <!-- Pitfall 4 mitigation: head-key dedupes across SPA navigation
             (without it, the meta tag stacks when a visitor lands here from
             another page that ships its own description). -->
        <meta head-key="description" name="description" :content="meta.description" />
    </Head>

    <PublicLayout>
        <section class="max-w-5xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ meta.title }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ meta.description }}
                </p>
            </header>

            <!-- Category filter pills row (plan 07-10 must_haves.truths line 33). -->
            <nav
                v-if="hasCategories"
                class="flex flex-wrap items-center gap-2"
                :aria-label="t('cms.blog.category_filter.label')"
                data-test="category-filter"
            >
                <CategoryFilterPill
                    :slug="null"
                    :label="t('cms.blog.category_filter.all')"
                    :active="activeCategory === null"
                />
                <CategoryFilterPill
                    v-for="category in categories"
                    :key="category.id"
                    :slug="category.slug"
                    :label="category.name"
                    :active="activeCategory === category.slug"
                />
            </nav>

            <!-- Card grid. -->
            <div
                v-if="hasArticles"
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
                data-test="article-grid"
            >
                <ArticleCard
                    v-for="article in articles"
                    :key="article.id"
                    :article="article"
                />
            </div>

            <p
                v-else
                class="text-base text-[var(--color-text-muted)] py-12 text-center"
                data-test="article-empty"
            >
                {{ t('cms.blog.empty.label') }}
            </p>

            <!-- Pagination. -->
            <nav
                v-if="hasMultiplePages"
                class="flex items-center justify-between gap-4 pt-4 border-t border-[var(--color-border)]"
                :aria-label="t('cms.blog.pagination.next')"
                data-test="article-pagination"
            >
                <button
                    type="button"
                    :disabled="!hasPrev"
                    class="px-3 py-1 text-sm font-semibold rounded-md border border-[var(--color-border)] disabled:opacity-50 disabled:cursor-not-allowed hover:text-[var(--color-text)]"
                    @click="goToPage(pagination.currentPage - 1)"
                >
                    {{ t('cms.blog.pagination.prev') }}
                </button>
                <span class="text-sm text-[var(--color-text-muted)]">
                    {{ pagination.currentPage }} / {{ pagination.lastPage }}
                </span>
                <button
                    type="button"
                    :disabled="!hasNext"
                    class="px-3 py-1 text-sm font-semibold rounded-md border border-[var(--color-border)] disabled:opacity-50 disabled:cursor-not-allowed hover:text-[var(--color-text)]"
                    @click="goToPage(pagination.currentPage + 1)"
                >
                    {{ t('cms.blog.pagination.next') }}
                </button>
            </nav>
        </section>
    </PublicLayout>
</template>

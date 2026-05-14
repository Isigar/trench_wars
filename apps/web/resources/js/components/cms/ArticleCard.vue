<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 33 (ArticleCard.vue —
     hero thumb + title + excerpt + meta + read-more on a paginated grid).

     Props typed via App.Data.ArticleSummaryData (07-09 DTO; 9 fields). No
     bodyHtml on this DTO — the index page deliberately ships a lighter shape
     than PublicArticleData (D-07-09-A — saves ~3-15kB per row and one
     tiptap_converter call per render). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Link } from '@inertiajs/vue3';

type ArticleSummaryData = App.Data.ArticleSummaryData;

defineProps<{
    article: ArticleSummaryData;
}>();

const { t } = useT();
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] overflow-hidden"
        data-test="article-card"
    >
        <Link :href="article.url" class="block">
            <img
                v-if="article.heroThumbUrl"
                :src="article.heroThumbUrl"
                :alt="t('cms.article.hero_alt.label')"
                class="w-full h-40 object-cover"
                loading="lazy"
            />
            <div v-else class="w-full h-40 bg-[var(--color-surface-alt,var(--color-surface))]" aria-hidden="true"></div>
        </Link>

        <div class="flex flex-col gap-2 px-4 pb-4">
            <Link
                :href="article.url"
                class="text-lg font-semibold text-[var(--color-text)] hover:underline focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            >
                {{ article.title }}
            </Link>

            <p v-if="article.excerpt" class="text-sm text-[var(--color-text-muted)] line-clamp-3">
                {{ article.excerpt }}
            </p>

            <div class="flex flex-wrap items-center gap-2 text-xs text-[var(--color-text-muted)]">
                <span>{{ t('cms.article.meta.category') }} {{ article.categoryName }}</span>
                <span aria-hidden="true">·</span>
                <span v-if="article.authorName">{{ t('cms.article.meta.author') }} {{ article.authorName }}</span>
                <span v-if="article.publishedAt" aria-hidden="true">·</span>
                <time v-if="article.publishedAt" :datetime="article.publishedAt">{{ article.publishedAt }}</time>
            </div>

            <Link
                :href="article.url"
                class="self-start text-sm font-semibold text-[var(--color-accent)] hover:underline focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            >
                {{ t('cms.blog.read_more.label') }}
            </Link>
        </div>
    </article>
</template>

<!-- Source: 07-10-PLAN.md <interfaces> Articles/Show.vue verbatim + Pitfall 10
     mitigation chain + 07-12-PLAN.md task 1 (Inertia Head with head-key on every
     meta tag — Pitfall 4 dedupe).

     v-html="article.bodyHtml" is safe-by-construction:
       1. Tiptap editor profile pinned in 07-01 (no iframe/script extensions loaded).
       2. ->profile('default') on the form field in 07-05.
       3. tiptap_converter()->asHTML server-side render in PublicArticleData::fromModel
          (07-05) — drops unknown nodes silently at parse time.
       4. ArticleShowPageTest HTTP-layer assertion that bodyHtml contains no
          <iframe or <script substring (plan 07-10 task 2).

     Head + head-key (Pitfall 4 mitigation — without head-key Inertia stacks
     meta tags across SPA navigation): every <meta> below carries a unique
     head-key so a second visit to a different article REPLACES rather than
     appends. ArticleHeadMetaTest asserts occurrence-count of og:title is
     exactly 1 after two visits. -->
<script setup lang="ts">
import ReportButton from '@/components/ReportButton.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type PublicArticleData = App.Data.PublicArticleData;

const props = defineProps<{
    article: PublicArticleData;
}>();

const { t } = useT();

// Excerpt is nullable on the data class; meta description should never be
// empty — fall back to the title so crawlers always have a description.
const metaDescription = computed<string>(() => props.article.excerpt ?? props.article.title);
const ogImage = computed<string>(() => props.article.heroOgImageUrl ?? '');
</script>

<template>
    <Head :title="article.title">
        <meta head-key="description" name="description" :content="metaDescription" />
        <meta head-key="og:title" property="og:title" :content="article.title" />
        <meta head-key="og:description" property="og:description" :content="metaDescription" />
        <meta head-key="og:image" property="og:image" :content="ogImage" />
        <meta head-key="og:url" property="og:url" :content="article.url" />
        <meta head-key="og:type" property="og:type" content="article" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:image" name="twitter:image" :content="ogImage" />
    </Head>

    <PublicLayout>
        <article class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-4" data-test="article-show">
            <img
                v-if="article.heroOgImageUrl"
                :src="article.heroOgImageUrl"
                :alt="t('cms.article.hero_alt.label')"
                class="w-full h-auto rounded-lg mb-2"
                loading="eager"
            />

            <h1 class="font-sans font-semibold text-[32px] leading-[1.15] tracking-tight text-[var(--color-text)]">
                {{ article.title }}
            </h1>

            <div class="flex flex-wrap items-center gap-2 text-sm text-[var(--color-text-muted)] mb-2">
                <span>{{ t('cms.article.meta.category') }} {{ article.categoryName }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ t('cms.article.meta.author') }} {{ article.authorName ?? '—' }}</span>
                <span aria-hidden="true">·</span>
                <time v-if="article.publishedAt" :datetime="article.publishedAt">
                    {{ t('cms.article.meta.published_on') }} {{ article.publishedAt }}
                </time>
            </div>

            <!-- v-html safe: bodyHtml is server-rendered tiptap_converter output;
                 Tiptap profile pins safe nodes (Pitfall 10 mitigation chain). -->
            <div
                class="prose prose-lg max-w-none text-[var(--color-text)]"
                data-test="article-body"
                v-html="article.bodyHtml"
            ></div>

            <!-- Plan 09-11 — inline report CTA for authenticated visitors. -->
            <div class="pt-4 border-t border-[var(--color-border)]">
                <ReportButton
                    target-type="App\Models\Article"
                    :target-id="article.id"
                    :target-name="article.title"
                />
            </div>
        </article>
    </PublicLayout>
</template>

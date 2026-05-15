<!--
  Source: .planning/phases/09-polish/09-09-PLAN.md task 2 + 09-RESEARCH.md
  Pattern 5 (WebP variant via medialibrary conversion).

  Renders an article cover via the Spatie medialibrary `cover-*` WebP conversion
  set (registered in App\Models\Article::registerMediaConversions — plan task 1).
  Banner-shaped variants:
    - thumb 200x120 — minimal row item
    - card  600x400 — article-index grid card
    - hero  1200x630 — article show-page banner (OpenGraph optimal dimensions)

  Open Question 1 LOCKED: WebP only, no JPEG fallback v1.

  Consumption pattern (plan key_links — pattern `conversions['cover-...']`):
    <ArticleCover :article="article" variant="card" />

  Resolution order:
    1. Explicit `:coverUrl` prop — caller hands a resolved URL string.
    2. `article.heroThumbUrl` — Phase 7 DTO field (extended in plan 09-09 to be
       a WebP URL since the Article 'thumb' conversion now applies ->format('webp')).
       Used when variant='card' (closest dimension match: thumb→600x400).
    3. `article.media[0]?.conversions['cover-' + variant]` — Spatie serializer
       shape (forward-compat).

  No fallback initials — articles without a cover render an empty surface placeholder
  (existing project idiom in ArticleCard.vue).
-->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type ArticleSummaryData = App.Data.ArticleSummaryData;
type PublicArticleData = App.Data.PublicArticleData;

type MediaConversions = Record<string, string>;
type MediaItem = { conversions?: MediaConversions };
type ArticleLike = (ArticleSummaryData | PublicArticleData) & { media?: MediaItem[] };

type Variant = 'thumb' | 'card' | 'hero';

const props = withDefaults(
    defineProps<{
        article: ArticleLike;
        variant?: Variant;
        coverUrl?: string | null;
    }>(),
    {
        variant: 'card',
        coverUrl: null,
    },
);

const { t } = useT();

// Variant → pixel dimensions (matches Article::registerMediaConversions cover-*
// trio exactly: thumb 200x120, card 600x400, hero 1200x630).
const dimensions: Record<Variant, { width: number; height: number }> = {
    thumb: { width: 200, height: 120 },
    card: { width: 600, height: 400 },
    hero: { width: 1200, height: 630 },
};

const dim = computed(() => dimensions[props.variant]);

// Resolution order: explicit coverUrl → DTO heroThumbUrl (only for card variant,
// dimension match) → conversions['cover-' + variant].
const resolvedUrl = computed<string | null>(() => {
    if (props.coverUrl) {
        return props.coverUrl;
    }
    // ArticleSummaryData.heroThumbUrl is the Phase 7 DTO field, now WebP-backed.
    // It maps to the 'thumb' conversion (600x400) which matches our card variant.
    if (props.variant === 'card' && 'heroThumbUrl' in props.article && props.article.heroThumbUrl) {
        return props.article.heroThumbUrl;
    }
    const conversionKey = `cover-${props.variant}`;
    return props.article.media?.[0]?.conversions?.[conversionKey] ?? null;
});

const altText = computed(() => t('cms.article.hero_alt.label'));
</script>

<template>
    <img
        v-if="resolvedUrl"
        :src="resolvedUrl"
        :alt="altText"
        :width="dim.width"
        :height="dim.height"
        loading="lazy"
        decoding="async"
        class="w-full object-cover"
        data-test="article-cover"
    />
    <div
        v-else
        :style="{ aspectRatio: `${dim.width} / ${dim.height}` }"
        class="w-full bg-[var(--color-surface-alt,var(--color-surface))]"
        aria-hidden="true"
        data-test="article-cover-fallback"
    />
</template>

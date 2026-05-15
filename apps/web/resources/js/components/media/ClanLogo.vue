<!--
  Source: .planning/phases/09-polish/09-09-PLAN.md task 2 + 09-RESEARCH.md
  Pattern 5 (WebP variant via medialibrary conversion).

  Renders a clan logo via the Spatie medialibrary `avatar-*` WebP conversion set
  (registered in App\Models\Clan::registerMediaConversions — plan task 1).

  Open Question 1 LOCKED: WebP only, no <picture> + JPEG fallback in v1
  (browser support >99% per RESEARCH; revisit if monitoring shows >0.5% failure
  rate post-launch).

  Consumption pattern (plan key_links — pattern `conversions['avatar-...']`):
    <ClanLogo :clan="clan" variant="card" />

  The component reads the WebP URL from one of two prop shapes, in priority:

    1. Explicit `:logoUrl` prop — caller hands a fully-resolved URL string. The
       common v1 path while ClanData DTO continues to expose a single
       `logoUrl` (forward-compat retained — DTO field name is what the controller
       picks).

    2. `clan.media[0]?.conversions['avatar-' + variant]` — for callers that
       pass the Eloquent-shaped `clan.media` array (Spatie medialibrary
       serializer output). This matches the RESEARCH Pattern 5 dispatch
       verbatim. When `clan.media` is exposed by a future DTO revision,
       components consume it transparently with no caller-side change.

  Renders all required Pattern 5 attributes: width + height (variant-mapped),
  loading="lazy", decoding="async". Fallback when neither URL source is present:
  initials placeholder (existing project idiom — first 2 chars of clan.name).
-->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type ClanData = App.Data.ClanData;

// Optional `media` shape — Spatie medialibrary serializer output (forward-compat
// for when ClanData starts exposing `media[]`).
type MediaConversions = Record<string, string>;
type MediaItem = { conversions?: MediaConversions };
type ClanWithMaybeMedia = ClanData & { media?: MediaItem[] };

type Variant = 'thumb' | 'card' | 'hero';

const props = withDefaults(
    defineProps<{
        clan: ClanWithMaybeMedia;
        variant?: Variant;
        // Optional explicit URL — caller may pass a single resolved URL
        // (current ClanData shape has no `media[]` so this is the v1 path).
        logoUrl?: string | null;
    }>(),
    {
        variant: 'card',
        logoUrl: null,
    },
);

const { t } = useT();

// Variant → pixel dimensions (matches Clan::registerMediaConversions exactly).
const dimensions: Record<Variant, { width: number; height: number }> = {
    thumb: { width: 48, height: 48 },
    card: { width: 200, height: 200 },
    hero: { width: 800, height: 800 },
};

const dim = computed(() => dimensions[props.variant]);

// Resolution order: explicit logoUrl prop, then `media[0].conversions['avatar-<variant>']`.
const resolvedUrl = computed<string | null>(() => {
    if (props.logoUrl) {
        return props.logoUrl;
    }
    const conversionKey = `avatar-${props.variant}`;
    return props.clan.media?.[0]?.conversions?.[conversionKey] ?? null;
});

// Initials fallback — first 2 word-initials of clan.name (matches ClanCard idiom).
const initials = computed(() =>
    props.clan.name
        .split(' ')
        .slice(0, 2)
        .map((w: string) => w[0] ?? '')
        .join('')
        .toUpperCase(),
);

const altText = computed(() => t('clans.logo_alt', { name: props.clan.name }));
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
        class="object-cover rounded-lg bg-[var(--color-surface-elevated)]"
        data-test="clan-logo"
    />
    <div
        v-else
        :style="{ width: `${dim.width}px`, height: `${dim.height}px` }"
        class="rounded-lg flex items-center justify-center shrink-0
               bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
               font-semibold select-none"
        aria-hidden="true"
        data-test="clan-logo-fallback"
    >
        {{ initials }}
    </div>
</template>

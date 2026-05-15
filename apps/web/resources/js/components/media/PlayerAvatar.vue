<!--
  Source: .planning/phases/09-polish/09-09-PLAN.md task 2 + 09-RESEARCH.md
  Pattern 5 (WebP variant via medialibrary conversion).

  Renders a player avatar via the Spatie medialibrary `avatar-*` WebP conversion
  set (registered in App\Models\Player::registerMediaConversions — plan task 1).
  Variant dimensions match Clan::registerMediaConversions for visual parity
  across player + clan avatars.

  Open Question 1 LOCKED: WebP only, no JPEG fallback in v1.

  Consumption pattern (plan key_links — pattern `conversions['avatar-...']`):
    <PlayerAvatar :player="player" variant="card" />

  Resolution order (identical to ClanLogo for consistency):
    1. Explicit `:avatarUrl` prop — caller hands a resolved URL string. The
       common v1 path while PublicPlayerData exposes a flat `avatarUrl`.
    2. `player.media[0]?.conversions['avatar-' + variant]` — Spatie serializer
       shape (forward-compat).

  Players use `rounded-full` (circular) vs Clans `rounded-lg` (square) per
  02-UI-SPEC.md § Component Inventory.
-->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

type PublicPlayerData = App.Data.PublicPlayerData;

type MediaConversions = Record<string, string>;
type MediaItem = { conversions?: MediaConversions };
type PlayerWithMaybeMedia = PublicPlayerData & { media?: MediaItem[] };

type Variant = 'thumb' | 'card' | 'hero';

const props = withDefaults(
    defineProps<{
        player: PlayerWithMaybeMedia;
        variant?: Variant;
        avatarUrl?: string | null;
    }>(),
    {
        variant: 'card',
        avatarUrl: null,
    },
);

const { t } = useT();

// Variant → pixel dimensions (matches Player::registerMediaConversions exactly,
// which deliberately matches Clan's set for cross-surface visual parity).
const dimensions: Record<Variant, { width: number; height: number }> = {
    thumb: { width: 48, height: 48 },
    card: { width: 200, height: 200 },
    hero: { width: 800, height: 800 },
};

const dim = computed(() => dimensions[props.variant]);

// Resolution order: explicit prop → DTO avatarUrl fallback → media conversions key.
const resolvedUrl = computed<string | null>(() => {
    if (props.avatarUrl) {
        return props.avatarUrl;
    }
    // PublicPlayerData.avatarUrl is the canonical v1 field — empty string means absent.
    if (props.player.avatarUrl && props.player.avatarUrl !== '') {
        return props.player.avatarUrl;
    }
    const conversionKey = `avatar-${props.variant}`;
    return props.player.media?.[0]?.conversions?.[conversionKey] ?? null;
});

// Initials fallback from displayName (matches PlayerCard idiom).
const initials = computed(() =>
    props.player.displayName
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0] ?? '')
        .join('')
        .toUpperCase(),
);

const altText = computed(() => t('players.avatar_alt', { name: props.player.displayName }));
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
        class="object-cover rounded-full bg-[var(--color-surface-elevated)]"
        data-test="player-avatar"
    />
    <div
        v-else
        :style="{ width: `${dim.width}px`, height: `${dim.height}px` }"
        class="rounded-full flex items-center justify-center shrink-0
               bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
               font-semibold select-none"
        aria-hidden="true"
        data-test="player-avatar-fallback"
    >
        {{ initials }}
    </div>
</template>

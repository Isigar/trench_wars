<!-- Source: 04-11-PLAN.md Task 2 + 04-RESEARCH.md § Pattern 7 (SlotOccupantPill 3-branch spec).

     Privacy contract reminder (T-04-11-01):
       PublicMatchOccupantData uses NULLABLE fields (`displayName: string | null`),
       NOT undefined. The server-side privacy gate (plan 04-10 +
       PublicMatchOccupantData::fromMatchSlot) sets displayName=null when the
       viewer cannot see the occupant's name. We test `!== null` (matching the
       generated TS type from spatie/laravel-data) rather than `!== undefined`.
       D-008 carve-out: clanTag may be non-null even when displayName is null. -->
<script setup lang="ts">
import ClanTagBadge from '@/components/clans/ClanTagBadge.vue';
import { useT } from '@/composables/useT';
import { computed } from 'vue';

const { t } = useT();

type PublicMatchOccupantData = App.Data.PublicMatchOccupantData;
type ClanTagData = App.Data.ClanTagData;

const props = defineProps<{
    slot: PublicMatchOccupantData;
}>();

// Branch detection — driven by the privacy-stripped DTO fields (null = withheld).
const hasIdentifiedOccupant = computed<boolean>(
    () => props.slot.displayName !== null,
);
const hasAnonymousOccupant = computed<boolean>(
    () => props.slot.displayName === null && props.slot.clanTag !== null,
);

// Synthetic ClanTagBadge.tag prop — the DTO surfaces clanTag as a raw
// string (the clan's 4-character tag) and clanSlug as the URL slug. The
// existing ClanTagBadge component expects a full ClanTagData shape; we
// adapt by constructing a minimal tag-like object.
const syntheticClanTag = computed<ClanTagData | null>(() => {
    if (props.slot.clanTag === null) return null;
    return {
        id: props.slot.clanSlug ?? props.slot.clanTag,
        slug: props.slot.clanSlug ?? props.slot.clanTag,
        label: { en: props.slot.clanTag },
        color: null,
    };
});
</script>

<template>
    <!-- Branch 1: identified occupant — link to /players/{slug} when slug present. -->
    <div
        v-if="hasIdentifiedOccupant"
        class="flex items-center gap-2 px-3 py-2 rounded-md
               bg-[var(--color-surface)] border border-[var(--color-border)]
               text-base text-[var(--color-text)]"
    >
        <a
            v-if="slot.playerSlug !== null"
            :href="`/players/${slot.playerSlug}`"
            class="text-[var(--color-text)] hover:underline truncate
                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
        >
            {{ slot.displayName }}
        </a>
        <span v-else class="truncate">{{ slot.displayName }}</span>

        <ClanTagBadge
            v-if="syntheticClanTag !== null"
            :tag="syntheticClanTag"
            as="span"
        />

        <span
            v-if="slot.isViewer"
            class="text-xs text-[var(--color-text-muted)] ml-1"
        >
            {{ t('matches.show.you_marker') }}
        </span>
    </div>

    <!-- Branch 2: anonymous occupant + clan tag (D-008 — clan tag stays public). -->
    <div
        v-else-if="hasAnonymousOccupant"
        class="flex items-center gap-2 px-3 py-2 rounded-md
               bg-[var(--color-surface)] border border-[var(--color-border)]
               text-base text-[var(--color-text-muted)]"
    >
        <span>{{ t('matches.show.slot_taken_anonymous') }}</span>
        <ClanTagBadge
            v-if="syntheticClanTag !== null"
            :tag="syntheticClanTag"
            as="span"
        />
    </div>

    <!-- Branch 3: empty slot — open for signup. -->
    <div
        v-else
        class="flex items-center gap-2 px-3 py-2 rounded-md
               bg-[var(--color-surface)] border border-dashed border-[var(--color-border)]
               text-base text-[var(--color-text-muted)]"
    >
        <span>{{ t('matches.show.slot_open') }}</span>
    </div>
</template>

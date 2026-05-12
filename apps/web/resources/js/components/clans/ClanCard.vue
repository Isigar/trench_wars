<!-- Source: 02-UI-SPEC.md § ClanCard component + § Page: /clans. -->
<script setup lang="ts">
import ClanTagBadge from '@/components/clans/ClanTagBadge.vue';
import { useT } from '@/composables/useT';
import type { App } from '@/types/api';
import { computed } from 'vue';

const { t } = useT();

// Use the generated DTO type from api.d.ts
type ClanData = App.Data.ClanData;

const props = defineProps<{
    clan: ClanData;
}>();

// Description truncated to 80 characters with ellipsis per UI-SPEC § ClanCard.
const descriptionExcerpt = computed(() => {
    const desc = props.clan.description?.en ?? '';
    if (desc.length <= 80) return desc;
    return desc.substring(0, 80) + '…';
});

// Avatar initials fallback — first 2 characters of clan name.
const initials = computed(() =>
    props.clan.name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);

// Member count string using pluralization convention (UI-SPEC).
const memberCountLabel = computed(() => {
    const count = props.clan.active_member_count;
    if (count === 1) return t('clans.members.count_one');
    return t('clans.members.count_other', { count });
});
</script>

<template>
    <!-- Source: 02-UI-SPEC.md § ClanCard component — entire card is an <a> link. -->
    <a
        :href="`/clans/${clan.slug}`"
        class="flex flex-col gap-3 p-4 rounded-lg
               bg-[var(--color-surface)] border border-[var(--color-border)]
               hover:bg-[var(--color-surface-elevated)] hover:border-[var(--color-accent)]
               transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
    >
        <!-- Avatar 48×48 rounded-lg with initials fallback -->
        <div
            class="w-12 h-12 rounded-lg flex items-center justify-center shrink-0
                   bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
                   text-xl font-semibold select-none"
            aria-hidden="true"
        >
            {{ initials }}
        </div>

        <!-- Clan name — Heading 20px/600 -->
        <h2 class="text-xl font-semibold text-[var(--color-text)] leading-[1.3]">
            {{ clan.name }}
        </h2>

        <!-- Tag badges -->
        <div v-if="clan.tags && clan.tags.length" class="flex flex-wrap gap-1">
            <ClanTagBadge
                v-for="tag in clan.tags"
                :key="tag.id"
                :tag="tag"
                as="span"
            />
        </div>

        <!-- Description excerpt — Body muted -->
        <p v-if="descriptionExcerpt" class="text-base text-[var(--color-text-muted)] line-clamp-2">
            {{ descriptionExcerpt }}
        </p>

        <!-- Member count + country flag -->
        <div class="flex items-center gap-2 text-base text-[var(--color-text-muted)]">
            <span>{{ memberCountLabel }}</span>
            <span v-if="clan.country_code" aria-hidden="true">·</span>
            <span v-if="clan.country_code">{{ clan.country_code }}</span>
        </div>
    </a>
</template>

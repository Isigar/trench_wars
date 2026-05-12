<!-- Source: 02-UI-SPEC.md § Component Inventory § PlayerCard + § Page: /players/{slug} (hero block). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { computed } from 'vue';

const { t } = useT();

// Use the generated DTO type from api.d.ts
type PublicPlayerData = App.Data.PublicPlayerData;
type ClanMembershipData = App.Data.ClanMembershipData;

const props = defineProps<{
    player: PublicPlayerData;
}>();

// Avatar initials fallback from display name.
const initials = computed(() =>
    props.player.displayName
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);
</script>

<template>
    <!-- Source: 02-UI-SPEC.md § Page: /players/{slug} hero block.
         Avatar 64×64 rounded-full (circular for players; contrast with clan rounded-lg).
         Discord tag, country flag, current clan are conditionally rendered using
         "absent ≠ null" contract — fields are UNDEFINED when withheld by privacy gate. -->
    <div class="flex flex-col sm:flex-row items-start gap-4">
        <!-- Avatar 64×64 desktop / 56×56 mobile, rounded-full -->
        <div
            class="w-14 h-14 sm:w-16 sm:h-16 rounded-full shrink-0
                   flex items-center justify-center
                   bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
                   text-xl font-semibold select-none"
            aria-hidden="true"
        >
            {{ initials }}
        </div>

        <!-- Name + meta -->
        <div class="flex flex-col gap-1">
            <!-- Display name — H1 Display 28px/600 (rendered by parent page as H1) -->
            <p class="text-[28px] font-semibold leading-[1.2] tracking-tight text-[var(--color-text)]">
                {{ player.displayName }}
            </p>

            <!-- Discord tag — shown only if present (absent ≠ null: undefined = withheld) -->
            <!-- T-02-08-02: NO v-if="player.showDiscordTag" — backend controls inclusion. -->
            <template v-if="player.discordTag !== undefined && player.discordTag !== null">
                <span class="font-mono text-sm font-semibold text-[var(--color-text-muted)]">
                    @{{ player.discordTag }}
                </span>
            </template>

            <!-- Country code -->
            <span v-if="player.countryCode" class="text-sm text-[var(--color-text-muted)]">
                {{ player.countryCode }}
            </span>

            <!-- Current clan link — only if present (absent when no active clan or withheld) -->
            <template v-if="player.currentClan !== undefined && player.currentClan !== null">
                <a
                    :href="`/clans/${(player.currentClan as ClanMembershipData).clan_id}`"
                    class="text-sm text-[var(--color-text)] hover:underline
                           focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                >
                    {{ (player.currentClan as ClanMembershipData).username ?? t('common.nav.clans') }}
                </a>
            </template>
        </div>
    </div>
</template>

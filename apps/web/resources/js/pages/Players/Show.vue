<!-- Source: 02-UI-SPEC.md § Page: /players/{slug} (Public player profile). -->
<script setup lang="ts">
import PlayerCard from '@/components/players/PlayerCard.vue';
import { useT } from '@/composables/useT';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

const { t } = useT();

// Use the generated DTO type from api.d.ts
// T-02-08-02: NEVER v-if on privacy flags.
// Backend has already stripped withheld fields — ABSENT (undefined) means withheld.
type PublicPlayerData = App.Data.PublicPlayerData;

defineProps<{
    player: PublicPlayerData;
}>();
</script>

<template>
    <Head :title="player.displayName" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-8">

            <!-- Own-profile privacy notice (isOwnProfile only) -->
            <!-- T-02-08-02: v-if on data field, not on privacy flag. -->
            <template v-if="player.isOwnProfile">
                <div
                    class="bg-[var(--color-surface)] border border-[var(--color-border)]
                           p-4 rounded-md text-base text-[var(--color-text-muted)]"
                >
                    {{ t('players.privacy.your_profile_note') }}
                </div>
            </template>

            <!-- Player hero block — composite component -->
            <div>
                <h1 class="sr-only">{{ player.displayName }}</h1>
                <PlayerCard :player="player" />
            </div>

            <!-- Bio section: only rendered if field is present in DTO (absent ≠ null). -->
            <!-- T-02-08-01: plain text, NO v-html. -->
            <template v-if="player.bio !== undefined && player.bio !== null">
                <div class="flex flex-col gap-2">
                    <p class="text-base text-[var(--color-text)] leading-relaxed whitespace-pre-wrap">
                        {{ player.bio.en ?? Object.values(player.bio)[0] }}
                    </p>
                </div>
            </template>

            <!-- Clan history: only if backend included it (show_clan_history permitted). -->
            <template v-if="player.clanHistory !== undefined && player.clanHistory !== null">
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('players.section.clan_history') }}
                    </h2>
                    <ul class="flex flex-col gap-2">
                        <li
                            v-for="(entry, idx) in player.clanHistory"
                            :key="idx"
                            class="text-base text-[var(--color-text-muted)]"
                        >
                            <!-- Clan history entries are generic — specific rendering deferred to Phase 3. -->
                            {{ JSON.stringify(entry) }}
                        </li>
                    </ul>
                </div>
            </template>

            <!-- Match history placeholder: only if backend included it. -->
            <template v-if="player.matchHistory !== undefined && player.matchHistory !== null">
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('players.section.match_history') }}
                    </h2>
                    <div class="bg-[var(--color-surface)] p-4 rounded-lg">
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('players.match_history.placeholder') }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- Stats placeholder: only if backend included it. -->
            <template v-if="player.stats !== undefined && player.stats !== null">
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('players.section.stats') }}
                    </h2>
                    <div class="bg-[var(--color-surface)] p-4 rounded-lg">
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('players.stats.placeholder') }}
                        </p>
                    </div>
                </div>
            </template>

        </section>
    </PublicLayout>
</template>

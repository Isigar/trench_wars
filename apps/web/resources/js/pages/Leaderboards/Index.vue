<!-- Source: .planning/phases/09-polish/09-06-PLAN.md task 2.
     Public Inertia page rendering the top-players + top-clans leaderboards
     with window (7d/30d/all) and game filter. Anonymous-friendly (D-018
     enforced inside LeaderboardEntryData::fromQueryResult). -->
<script setup lang="ts">
import LeaderboardTable from '@/components/LeaderboardTable.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import {
    TabsContent,
    TabsList,
    TabsRoot,
    TabsTrigger,
} from 'reka-ui';
import { computed, ref } from 'vue';

type LeaderboardEntryData = App.Data.LeaderboardEntryData;
type LeaderboardClanEntryData = App.Data.LeaderboardClanEntryData;

interface GameRow {
    id: string;
    key: string;
    name: Record<string, string> | string;
}

interface Filters {
    window: '7d' | '30d' | 'all';
    game: string | null;
    limit: number;
}

const props = defineProps<{
    players: LeaderboardEntryData[];
    clans: LeaderboardClanEntryData[];
    filters: Filters;
    games: GameRow[];
    allowed_windows: string[];
}>();

const { t } = useT();

// Local state mirrors the server-confirmed filter so the controls stay in
// sync after every router.get round-trip.
const selectedWindow = ref<string>(props.filters.window);
const selectedGame = ref<string>(props.filters.game ?? '');

const hasGames = computed<boolean>(() => props.games.length !== 0);

function localisedGameName(game: GameRow): string {
    if (typeof game.name === 'string') {
        return game.name;
    }
    return game.name.en ?? game.key;
}

function applyFilters(): void {
    const params: Record<string, string | number> = {
        window: selectedWindow.value,
    };
    if (selectedGame.value !== '') {
        params.game = selectedGame.value;
    }
    router.get('/leaderboards', params, { preserveScroll: true });
}

function selectWindow(window: string): void {
    selectedWindow.value = window;
    applyFilters();
}

function isActiveWindow(window: string): boolean {
    return selectedWindow.value === window;
}
</script>

<template>
    <Head :title="t('leaderboards.page.title')">
        <meta head-key="description" name="description" :content="t('leaderboards.page.description')" />
    </Head>

    <PublicLayout>
        <section class="max-w-5xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-3">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('leaderboards.page.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('leaderboards.page.description') }}
                </p>
            </header>

            <!-- Window + game filter row -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-md border border-[var(--color-border)] overflow-hidden">
                    <button
                        v-for="w in allowed_windows"
                        :key="w"
                        type="button"
                        :class="[
                            'px-3 py-1 text-sm font-semibold',
                            'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
                            'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
                            isActiveWindow(w)
                                ? 'bg-[var(--color-accent)] text-[var(--color-accent-fg)]'
                                : 'bg-[var(--color-surface)] text-[var(--color-text-muted)] hover:text-[var(--color-text)]',
                        ]"
                        :aria-pressed="isActiveWindow(w)"
                        @click="selectWindow(w)"
                    >
                        {{ t(`leaderboards.windows.${w}`) }}
                    </button>
                </div>

                <div v-if="hasGames" class="flex items-center gap-2">
                    <label for="game-filter" class="text-sm text-[var(--color-text-muted)]">
                        {{ t('common.nav.matches') }}
                    </label>
                    <select
                        id="game-filter"
                        v-model="selectedGame"
                        class="h-9 px-3 rounded-md text-sm text-[var(--color-text)]
                               bg-[var(--color-surface)] border border-[var(--color-border)]
                               focus:outline-2 focus:outline-[var(--color-focus-ring)]"
                        @change="applyFilters"
                    >
                        <option value="">{{ t('leaderboards.windows.all') }}</option>
                        <option v-for="game in games" :key="game.id" :value="game.id">
                            {{ localisedGameName(game) }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Tabs -->
            <TabsRoot default-value="players">
                <TabsList
                    class="flex gap-1 border-b border-[var(--color-border)] overflow-x-auto"
                    aria-label="Leaderboard tabs"
                >
                    <TabsTrigger
                        value="players"
                        class="h-10 px-4 text-sm font-semibold text-[var(--color-text-muted)]
                               border-b-2 border-transparent -mb-px whitespace-nowrap
                               data-[state=active]:border-[var(--color-accent)]
                               data-[state=active]:text-[var(--color-text)]
                               transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        {{ t('leaderboards.tabs.players') }}
                    </TabsTrigger>
                    <TabsTrigger
                        value="clans"
                        class="h-10 px-4 text-sm font-semibold text-[var(--color-text-muted)]
                               border-b-2 border-transparent -mb-px whitespace-nowrap
                               data-[state=active]:border-[var(--color-accent)]
                               data-[state=active]:text-[var(--color-text)]
                               transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        {{ t('leaderboards.tabs.clans') }}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="players" class="pt-6 focus-visible:outline-none">
                    <LeaderboardTable :rows="players" mode="players" />
                </TabsContent>

                <TabsContent value="clans" class="pt-6 focus-visible:outline-none">
                    <LeaderboardTable :rows="clans" mode="clans" />
                </TabsContent>
            </TabsRoot>
        </section>
    </PublicLayout>
</template>

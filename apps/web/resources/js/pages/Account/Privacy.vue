<!-- Self-service profile-privacy editor (D-018 — user-controllable per-section
     + global tier). Mirrors Account/NotificationPreferences: a global-tier
     <select> plus one Reka Switch per section; submit POSTs the full snapshot
     so the server persists the auth user's own PlayerPrivacy row. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { reactive, ref } from 'vue';

interface PrivacyState {
    show_to: string;
    show_real_name: boolean;
    show_discord_tag: boolean;
    show_clan_history: boolean;
    show_match_history: boolean;
    show_stats: boolean;
}

const props = defineProps<{
    privacy: PrivacyState;
    tiers: string[];
    sections: string[];
}>();

const { t } = useT();

const showTo = ref<string>(props.privacy.show_to);

// Per-section booleans in a flat Record so each toggle binds a typed lvalue.
const sectionState = reactive<Record<string, boolean>>(
    Object.fromEntries(
        props.sections.map((section) => [
            section,
            Boolean((props.privacy as unknown as Record<string, boolean>)[section]),
        ]),
    ),
);

function submit(): void {
    router.post(
        route('account.privacy.update'),
        { show_to: showTo.value, ...sectionState },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('players.privacy.editor.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('players.privacy.editor.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('players.privacy.editor.description') }}
                </p>
            </header>

            <form class="flex flex-col gap-8" @submit.prevent="submit">
                <!-- Global tier -->
                <div class="flex flex-col gap-2">
                    <label
                        for="privacy-show-to"
                        class="text-sm font-semibold text-[var(--color-text)]"
                    >
                        {{ t('players.privacy.editor.show_to.label') }}
                    </label>
                    <select
                        id="privacy-show-to"
                        v-model="showTo"
                        class="h-10 px-3 rounded-md text-sm w-full md:w-80
                               bg-[var(--color-surface-elevated)] text-[var(--color-text)]
                               border border-[var(--color-border)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        <option v-for="tier in tiers" :key="tier" :value="tier">
                            {{ t(`players.privacy.editor.show_to.options.${tier}`) }}
                        </option>
                    </select>
                    <p class="text-xs text-[var(--color-text-muted)]">
                        {{ t('players.privacy.editor.show_to.help') }}
                    </p>
                </div>

                <!-- Per-section toggles -->
                <div class="flex flex-col gap-2">
                    <h2 class="text-sm font-semibold text-[var(--color-text)]">
                        {{ t('players.privacy.editor.sections.label') }}
                    </h2>
                    <p class="text-xs text-[var(--color-text-muted)] mb-2">
                        {{ t('players.privacy.editor.sections.help') }}
                    </p>

                    <div
                        v-for="section in sections"
                        :key="section"
                        class="flex items-center justify-between py-2 border-b border-[var(--color-border)] last:border-b-0"
                    >
                        <span class="text-sm text-[var(--color-text)]">
                            {{ t(`players.privacy.editor.sections.${section}`) }}
                        </span>
                        <SwitchRoot
                            v-model="sectionState[section]"
                            :aria-label="t(`players.privacy.editor.sections.${section}`)"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer
                                   rounded-full border-2 border-transparent
                                   transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]
                                   data-[state=checked]:bg-[var(--color-accent)]
                                   data-[state=unchecked]:bg-[var(--color-surface-elevated)]"
                        >
                            <SwitchThumb
                                class="pointer-events-none block h-5 w-5 rounded-full
                                       bg-white shadow-md
                                       transition-transform duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                                       data-[state=checked]:translate-x-5
                                       data-[state=unchecked]:translate-x-0"
                            />
                        </SwitchRoot>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 font-semibold rounded-md
                               h-10 px-4 text-sm
                               bg-[var(--color-accent)] text-[var(--color-accent-fg)]
                               hover:opacity-90
                               transition-[opacity,background-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        {{ t('players.privacy.editor.save') }}
                    </button>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>

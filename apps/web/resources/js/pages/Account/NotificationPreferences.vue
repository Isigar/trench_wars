<!-- Source: .planning/phases/09-polish/09-06-PLAN.md task 1.
     5×2 (event_type × channel) preference matrix. Each cell is a Reka
     Switch backed by a reactive ref; submit POSTs the full snapshot so the
     server can `updateOrCreate` every tuple in one transaction. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { computed, reactive } from 'vue';

interface PreferenceMatrix {
    [eventType: string]: {
        [channel: string]: boolean;
    };
}

const props = defineProps<{
    preferences: PreferenceMatrix;
    event_types: string[];
    channels: string[];
}>();

const { t } = useT();

// Reactive deep copy of the matrix so per-cell toggles bind via v-model.
const state = reactive<PreferenceMatrix>(
    Object.fromEntries(
        props.event_types.map((eventType) => [
            eventType,
            Object.fromEntries(
                props.channels.map((channel) => [channel, props.preferences[eventType]?.[channel] ?? false]),
            ),
        ]),
    ),
);

const flatTuples = computed(() => {
    const tuples: { event_type: string; channel: string; enabled: boolean }[] = [];
    for (const eventType of props.event_types) {
        for (const channel of props.channels) {
            tuples.push({
                event_type: eventType,
                channel,
                enabled: state[eventType]?.[channel] ?? false,
            });
        }
    }
    return tuples;
});

function submit(): void {
    router.post(
        route('account.notification-preferences.update'),
        { preferences: flatTuples.value },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('notifications.preferences.title')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('notifications.preferences.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('notifications.preferences.description') }}
                </p>
            </header>

            <form class="flex flex-col gap-2" @submit.prevent="submit">
                <div
                    class="grid gap-2 items-center"
                    :style="{ gridTemplateColumns: `minmax(0, 1fr) ${'auto '.repeat(channels.length).trim()}` }"
                >
                    <!-- Header row -->
                    <div></div>
                    <div
                        v-for="channel in channels"
                        :key="`hdr-${channel}`"
                        class="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)] text-center px-3"
                    >
                        {{ t(`notifications.preferences.channels.${channel}`) }}
                    </div>

                    <!-- Matrix rows -->
                    <template v-for="eventType in event_types" :key="eventType">
                        <div class="py-2 text-sm text-[var(--color-text)]">
                            {{ t(`notifications.preferences.events.${eventType}`) }}
                        </div>

                        <div
                            v-for="channel in channels"
                            :key="`${eventType}-${channel}`"
                            class="flex justify-center px-3"
                        >
                            <SwitchRoot
                                v-model="state[eventType][channel]"
                                :aria-label="`${t(`notifications.preferences.events.${eventType}`)} – ${t(`notifications.preferences.channels.${channel}`)}`"
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
                    </template>
                </div>

                <div class="flex justify-end pt-4">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 font-semibold rounded-md
                               h-10 px-4 text-sm
                               bg-[var(--color-accent)] text-[var(--color-accent-fg)]
                               hover:opacity-90
                               transition-[opacity,background-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        {{ t('notifications.preferences.save') }}
                    </button>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>

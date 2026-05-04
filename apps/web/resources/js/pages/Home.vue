<!-- Source: 01-UI-SPEC.md § Page: `/` (Home — logged-out state).
     NOTE: literal English strings are TEMPORARY placeholders. Plan 08 replaces every
     visible string with t('home.tagline') etc. This is acceptable in plan 07 because
     the NoHardcodedStringsTest comes online in plan 08. -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import Button from '@/components/ui/Button.vue';
</script>

<template>
    <Head title="Trenchwars" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-16 md:py-24">
            <div class="flex flex-col gap-6">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    The league for clan-organised matches.
                </h1>

                <p class="text-base text-[var(--color-text-muted)]">
                    Schedule scrims, sign up for slots, and let results write themselves.
                </p>

                <!-- TODO(plan-09): Wire to /auth/discord/redirect. -->
                <!-- TODO(plan-08): Replace literal strings with t('auth.discord.button_label') etc. -->
                <div>
                    <Button variant="primary" size="lg" disabled>
                        Log in with Discord
                    </Button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

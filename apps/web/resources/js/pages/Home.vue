<!-- Source: 01-UI-SPEC.md § Page: `/` (Home — logged-out state).
     Plan 08 replaced the temporary literal English placeholders from plan 07 with
     `t()` calls; the NoHardcodedStringsTest now enforces the contract. -->
<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head } from '@inertiajs/vue3';

const { t } = useT();
</script>

<template>
    <Head :title="t('common.brand.name')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-16 md:py-24">
            <div class="flex flex-col gap-6">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('home.tagline') }}
                </h1>

                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('home.subcopy') }}
                </p>

                <!-- TODO(plan-09): Wire to /auth/discord/redirect via Inertia <Link> or form submit. -->
                <div>
                    <Button variant="primary" size="lg" disabled>
                        {{ t('auth.discord.button_label') }}
                    </Button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>

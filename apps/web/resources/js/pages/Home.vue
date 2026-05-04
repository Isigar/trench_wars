<!-- Source: 01-UI-SPEC.md § Page: `/` (Home — logged-out + logged-in states).
     Plan 09 replaces the disabled placeholder Button from plan 08 with a real
     <LoginButton> and adds the logged-in welcome variant. Every visible string
     still flows through `t()` (NoHardcodedStringsTest from plan 08). -->
<script setup lang="ts">
import LoginButton from '@/components/LoginButton.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AuthUser {
    id: string;
    discord_id: string;
    username: string;
    avatar_url: string | null;
}

const { t } = useT();
const page = usePage();
const user = computed<AuthUser | null>(
    () => (page.props.auth as AuthUser | null) ?? null,
);
</script>

<template>
    <Head :title="t('common.brand.name')" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-16 md:py-24">
            <div class="flex flex-col gap-6">
                <template v-if="user">
                    <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                        {{ t('home.welcome_back', { name: user.username }) }}
                    </h1>
                    <p class="text-base text-[var(--color-text-muted)]">
                        {{ t('home.next_steps') }}
                    </p>
                </template>

                <template v-else>
                    <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                        {{ t('home.tagline') }}
                    </h1>
                    <p class="text-base text-[var(--color-text-muted)]">
                        {{ t('home.subcopy') }}
                    </p>
                    <div>
                        <LoginButton />
                    </div>
                </template>
            </div>
        </section>
    </PublicLayout>
</template>

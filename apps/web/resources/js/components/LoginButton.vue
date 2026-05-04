<script setup lang="ts">
import DiscordIcon from '@/components/icons/DiscordIcon.vue';
import { useT } from '@/composables/useT';

const { t } = useT();
</script>

<template>
    <!-- Source: 01-UI-SPEC.md § Page: `/` (Home — logged-out state) — primary CTA,
         accent fill, w-full on mobile, auto on md+, Discord icon at gap-2 inside button.
         Native <a> rather than Inertia <Link> because /auth/discord/redirect is a
         server-side full-document redirect to discord.com — Inertia would XHR it. -->
    <a
        href="/auth/discord/redirect"
        class="inline-flex items-center justify-center gap-2 h-12 px-6 text-base font-semibold rounded-md
               bg-[var(--color-accent)] text-[var(--color-accent-fg)]
               hover:opacity-90
               transition-[opacity,background-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
               w-full md:w-auto"
    >
        <DiscordIcon :size="20" />
        <span>{{ t('auth.discord.button_label') }}</span>
    </a>
</template>

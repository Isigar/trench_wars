<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Trenchwars" />

    <main>
        <h1>Trenchwars</h1>
        <p>The league for clan-organised matches.</p>
        <!-- TODO(plan-07): wrap with PublicLayout, apply Tailwind v4 tokens. -->
        <!-- TODO(plan-08): replace literal strings with t('home.tagline'), etc. -->
        <!-- TODO(plan-09): add "Log in with Discord" CTA wired to /auth/discord/redirect. -->
    </main>
</template>

<!-- Source: 02-09-PLAN.md Task 3 (stub) — full implementation in plan 02-12.
     This file is a minimal stub so Inertia's ensure_pages_exist test check passes.
     Plan 02-12 replaces this with the full My Clan management page. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { ClanData, ClanMembershipData } from '@/types/api';
import { Head } from '@inertiajs/vue3';

const { t } = useT();

defineProps<{
    membership: ClanMembershipData | null;
    clan: ClanData | null;
    members: ClanMembershipData[];
    invites: unknown[];
    applications: unknown[];
}>();
</script>

<template>
    <Head :title="t('clans.my_clan.title')" />

    <PublicLayout>
        <div class="max-w-5xl mx-auto px-4 py-10">
            <p class="text-[var(--color-text-muted)]">
                {{ t('clans.my_clan.title') }}
            </p>
        </div>
    </PublicLayout>
</template>

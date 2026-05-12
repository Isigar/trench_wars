<!-- Source: 02-UI-SPEC.md § Members section + § Component Inventory § MemberRow. -->
<script setup lang="ts">
import ClanRoleBadge from '@/components/clans/ClanRoleBadge.vue';

// Use the generated DTO type from api.d.ts
type ClanMembershipData = App.Data.ClanMembershipData;

// Avatar initials fallback from username.
function getInitials(name: string | null): string {
    if (!name) return '?';
    return name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}

const props = withDefaults(
    defineProps<{
        member: ClanMembershipData;
        /**
         * Show role management / remove actions. False on public pages, true on
         * My Clan members tab (plan 02-09). Emits change-role / remove when true.
         */
        showActions?: boolean;
    }>(),
    {
        showActions: false,
    },
);

const emit = defineEmits<{
    (e: 'change-role', membershipId: string, newRole: string): void;
    (e: 'remove', membershipId: string): void;
}>();
</script>

<template>
    <!-- Source: 02-UI-SPEC.md § Members section — avatar, name, role badge. -->
    <div
        class="flex items-center gap-4 p-4
               bg-[var(--color-surface)] border-b border-[var(--color-border)]"
    >
        <!-- Avatar 32×32 rounded-full with initials fallback -->
        <div
            class="w-8 h-8 rounded-full shrink-0 flex items-center justify-center
                   bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
                   text-xs font-semibold select-none"
            aria-hidden="true"
        >
            {{ getInitials(member.username) }}
        </div>

        <!-- Member name — link to player profile if slug present -->
        <div class="flex-1 min-w-0">
            <a
                v-if="member.player_slug"
                :href="`/players/${member.player_slug}`"
                class="text-base text-[var(--color-text)] hover:underline truncate block
                       focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            >
                {{ member.username ?? member.player_slug }}
            </a>
            <span v-else class="text-base text-[var(--color-text)] truncate block">
                {{ member.username ?? '—' }}
            </span>
        </div>

        <!-- Role badge -->
        <ClanRoleBadge :role="(member.role as 'leader' | 'officer' | 'member' | 'recruit')" />

        <!-- Actions slot (showActions=true used by plan 02-09 My Clan management) -->
        <template v-if="showActions">
            <slot name="actions" :member="member" :emit="emit" />
        </template>
    </div>
</template>

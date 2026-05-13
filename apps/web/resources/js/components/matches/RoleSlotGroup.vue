<!-- Source: 04-11-PLAN.md Task 2 + 04-RESEARCH.md § Pattern 7 (RoleSlotGroup spec). -->
<script setup lang="ts">
import SignupButton from '@/components/matches/SignupButton.vue';
import SlotOccupantPill from '@/components/matches/SlotOccupantPill.vue';
import { useT } from '@/composables/useT';
import { computed } from 'vue';

const { t } = useT();

type PublicMatchOccupantData = App.Data.PublicMatchOccupantData;

/**
 * RoleGroup shape — matches the role-grouped DTO that MatchShowController emits.
 * roleDisplayName is the per-locale translated map; we resolve `.en` with a fallback.
 */
export interface RoleGroup {
    gameRoleId: string;
    roleKey: string;
    roleDisplayName: Record<string, string> | null;
    sortOrder: number;
    slots: PublicMatchOccupantData[];
}

const props = defineProps<{
    group: RoleGroup;
    matchId: string;
    /** Whether the viewer is eligible to sign up (controller's computeSignupAllowed). */
    signupAllowed: boolean;
    /** When non-null, the viewer already occupies a slot somewhere in this match. */
    viewerSlotId: string | null;
}>();

const headerLabel = computed<string>(() => {
    return props.group.roleDisplayName?.en
        ?? Object.values(props.group.roleDisplayName ?? {})[0]
        ?? t('matches.show.role_unknown');
});

// At least one slot has no occupant — signup CTA only renders when a slot exists to fill.
const hasEmptySlot = computed<boolean>(
    () => props.group.slots.some((s) => s.displayName === null && s.clanTag === null),
);

// Signup CTA gating — UI hint, not authoritative. Mirrors the controller's
// computeSignupAllowed but additionally requires this role to have an empty slot.
const buttonEnabled = computed<boolean>(
    () => props.signupAllowed && hasEmptySlot.value && props.viewerSlotId === null,
);
</script>

<template>
    <section class="flex flex-col gap-3">
        <header class="flex items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-[var(--color-text)]">
                {{ headerLabel }}
            </h2>
            <SignupButton
                v-if="signupAllowed && hasEmptySlot && viewerSlotId === null"
                :match-id="matchId"
                :game-role-id="group.gameRoleId"
                :enabled="buttonEnabled"
            />
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <SlotOccupantPill
                v-for="slot in group.slots"
                :key="slot.slotId"
                :slot="slot"
            />
        </div>
    </section>
</template>

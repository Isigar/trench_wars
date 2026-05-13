<!-- Source: 04-11-PLAN.md Task 2 + 04-RESEARCH.md § Pattern 7 (SignupButton spec).

     Inertia router.post invokes POST /matches/{match}/signups (matches.signups.store).
     Backend errors (4 typed exceptions in plan 04-10's MatchSignupController) flow
     back as ValidationException → 422 → Inertia errors bag accessible via
     usePage().props.errors. We expose the role-specific error inline (the
     `game_role_id` error key is set by CapacityExceededException). Form-level
     errors (`general` key) display via the Show page's banner. -->
<script setup lang="ts">
import Button from '@/components/ui/Button.vue';
import { useT } from '@/composables/useT';
import { router } from '@inertiajs/vue3';

const { t } = useT();

const props = defineProps<{
    /** UUID of the GameMatch (route param). */
    matchId: string;
    /** UUID of the GameRole this signup will be assigned to. */
    gameRoleId: string;
    /** UI-only enablement; backend MatchSignupService remains the canonical guard. */
    enabled: boolean;
}>();

function signUp(): void {
    if (!props.enabled) return;
    router.post(
        route('matches.signups.store', { match: props.matchId }),
        { game_role_id: props.gameRoleId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Button
        variant="primary"
        size="sm"
        :disabled="!enabled"
        @click="signUp"
    >
        {{ t('matches.show.signup_button') }}
    </Button>
</template>

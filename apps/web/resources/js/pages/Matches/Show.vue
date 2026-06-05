<!-- Source: 04-11-PLAN.md Task 1 + 04-RESEARCH.md § Pattern 7 (Show.vue verbatim skeleton). -->
<script setup lang="ts">
import MatchStatusBadge from '@/components/matches/MatchStatusBadge.vue';
import RoleSlotGroup, { type RoleGroup } from '@/components/matches/RoleSlotGroup.vue';
import ReportButton from '@/components/ReportButton.vue';
import Button from '@/components/ui/Button.vue';
import Textarea from '@/components/ui/Textarea.vue';
import { useT } from '@/composables/useT';
import { Head, router, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { computed } from 'vue';

const { t } = useT();

type PublicMatchData = App.Data.PublicMatchData;

/**
 * Show.vue receives 4 props from MatchShowController. roleGroups is the
 * privacy-stripped role-grouped slot collection (Pattern 7); the per-occupant
 * privacy projection was done server-side via PlayerPrivacyGate (T-04-11-01).
 * The Vue layer NEVER re-derives privacy.
 */
const props = defineProps<{
    match: PublicMatchData;
    roleGroups: RoleGroup[];
    /** UI hint — mirrors MatchSignupService preconditions without acquiring a row lock. */
    signupAllowed: boolean;
    /** Set when the viewer occupies a slot — drives "Cancel signup" affordance. */
    viewerSlotId: string | null;
    /** UI hint — mirrors StoreMatchDisputeRequest::authorize (played + organiser/participant). */
    canDispute: boolean;
    /** True when the viewer already has an open dispute — swaps the form for a note. */
    hasOpenDispute: boolean;
}>();

const titleText = computed<string>(
    () => props.match.title?.en ?? t('matches.show.title_fallback'),
);

// Formatted scheduled_at — keep it simple via Intl; no extra dependency.
const scheduledAtLabel = computed<string>(() => {
    const d = new Date(props.match.scheduled_at);
    if (Number.isNaN(d.getTime())) return props.match.scheduled_at;
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(d);
});

const scheduledAtIso = computed<string>(() => {
    const d = new Date(props.match.scheduled_at);
    return Number.isNaN(d.getTime()) ? props.match.scheduled_at : d.toISOString();
});

const descriptionText = computed<string | null>(() => {
    const desc = props.match.description?.en;
    return desc && desc.length >= 1 ? desc : null;
});

// Boolean flag to keep template free of `>` comparison literals (NoHardcodedStringsTest
// regex misreads `>` in attribute values as start-of-text-node markers).
const hasRoleGroups = computed<boolean>(() => props.roleGroups.length >= 1);

function cancelSignup(): void {
    if (props.viewerSlotId === null) return;
    router.delete(
        route('matches.signups.destroy', { match: props.match.id, slot: props.viewerSlotId }),
        { preserveScroll: true },
    );
}

// Raise-a-dispute form (shown when canDispute && !hasOpenDispute).
const disputeForm = useForm({ body: '' });

function submitDispute(): void {
    disputeForm.post(route('matches.disputes.store', { match: props.match.id }), {
        preserveScroll: true,
        onSuccess: () => disputeForm.reset(),
    });
}
</script>

<template>
    <Head :title="titleText" />

    <PublicLayout>
        <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-8">

            <!-- Hero block: title + status + scheduled time. -->
            <header class="flex flex-col gap-3">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ titleText }}
                </h1>

                <div class="flex flex-wrap items-center gap-3">
                    <MatchStatusBadge :status="match.status" />
                    <time
                        :datetime="scheduledAtIso"
                        class="text-base text-[var(--color-text-muted)]"
                    >
                        {{ scheduledAtLabel }}
                    </time>
                </div>

                <!-- Cancel signup affordance — visible when viewer occupies a slot. -->
                <div v-if="viewerSlotId !== null" class="mt-2">
                    <Button variant="secondary" size="sm" @click="cancelSignup">
                        {{ t('matches.show.cancel_signup_button') }}
                    </Button>
                </div>
            </header>

            <!-- Description block — plain text, NO v-html (T-04-11-02 reuse of Phase 2 Pitfall 3). -->
            <div v-if="descriptionText !== null" class="flex flex-col gap-2">
                <h2 class="text-xl font-semibold text-[var(--color-text)]">
                    {{ t('matches.show.description_heading') }}
                </h2>
                <p class="text-base text-[var(--color-text)] leading-relaxed whitespace-pre-wrap">
                    {{ descriptionText }}
                </p>
            </div>

            <!-- Role-grouped slot grid — privacy-stripped DTO collection from controller. -->
            <div v-if="hasRoleGroups" class="flex flex-col gap-6">
                <RoleSlotGroup
                    v-for="group in roleGroups"
                    :key="group.gameRoleId"
                    :group="group"
                    :match-id="match.id"
                    :signup-allowed="signupAllowed"
                    :viewer-slot-id="viewerSlotId"
                />
            </div>

            <!-- Defensive empty state — should not happen in practice (matches always seeded with slots). -->
            <div
                v-else
                role="status"
                class="py-12 text-center"
            >
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('matches.show.no_roles_yet') }}
                </p>
            </div>

            <!-- Raise-a-dispute surface — reachable entry point into the moderator
                 dispute queue. Eligibility mirrored server-side in
                 StoreMatchDisputeRequest::authorize. -->
            <div
                v-if="canDispute"
                class="pt-4 border-t border-[var(--color-border)] flex flex-col gap-3"
            >
                <div class="flex flex-col gap-1">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('matches.dispute.heading') }}
                    </h2>
                    <p class="text-sm text-[var(--color-text-muted)]">
                        {{ t('matches.dispute.help') }}
                    </p>
                </div>

                <p
                    v-if="hasOpenDispute"
                    class="text-sm text-[var(--color-text-muted)] italic"
                >
                    {{ t('matches.dispute.pending_note') }}
                </p>

                <form
                    v-else
                    class="flex flex-col gap-3"
                    @submit.prevent="submitDispute"
                >
                    <Textarea
                        id="dispute-body"
                        v-model="disputeForm.body"
                        :label="t('matches.dispute.body_label')"
                        :placeholder="t('matches.dispute.body_placeholder')"
                        :rows="4"
                        :errors="disputeForm.errors.body ? [disputeForm.errors.body] : []"
                    />
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            variant="primary"
                            :disabled="disputeForm.processing"
                        >
                            {{ t('matches.dispute.submit') }}
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Plan 09-11 — inline report CTA for authenticated visitors.
                 D-04-03-A LOCKED: target_type is the FQN `App\Models\GameMatch`
                 (Match is a PHP 8 reserved keyword). -->
            <div class="pt-4 border-t border-[var(--color-border)]">
                <ReportButton
                    target-type="App\Models\GameMatch"
                    :target-id="match.id"
                />
            </div>

        </section>
    </PublicLayout>
</template>

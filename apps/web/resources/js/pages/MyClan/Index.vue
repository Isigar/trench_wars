<!-- Source: 02-11-PLAN.md Task 2 + 02-UI-SPEC.md § Page: /my-clan
     4-tab management UI for clan Leaders and Officers.
     No-clan state for users without an active membership.
     Rule 2 amendment: invite modal uses invited_username (resolved server-side via StoreClanInviteRequest). -->
<script setup lang="ts">
import { ref } from 'vue';
import { useForm, router, Head } from '@inertiajs/vue3';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import TabGroup from '@/components/ui/TabGroup.vue';
import Modal from '@/components/ui/Modal.vue';
import MemberRow from '@/components/clans/MemberRow.vue';
import Button from '@/components/ui/Button.vue';
import TextInput from '@/components/ui/TextInput.vue';
import Textarea from '@/components/ui/Textarea.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';

const { t } = useT();

type ClanData = App.Data.ClanData;
type ClanMembershipData = App.Data.ClanMembershipData;
type ClanInviteData = App.Data.ClanInviteData;
type ClanApplicationData = App.Data.ClanApplicationData;
type ReceivedClanInviteData = App.Data.ReceivedClanInviteData;
type MyClanApplicationData = App.Data.MyClanApplicationData;

const props = defineProps<{
    clan: ClanData | null;
    membership: ClanMembershipData | null;
    members: ClanMembershipData[];
    invites: ClanInviteData[];
    applications: ClanApplicationData[];
    received_invites: ReceivedClanInviteData[];
    my_applications: MyClanApplicationData[];
}>();

// ---------------------------------------------------------------------------
// No-clan create form
// ---------------------------------------------------------------------------
const createForm = useForm({
    name: '',
    tag: '',
    description: '',
});

function createClan(): void {
    createForm.post(route('clans.store'));
}

// ---------------------------------------------------------------------------
// Profile tab
// ---------------------------------------------------------------------------
const profileForm = useForm({
    name: props.clan?.name ?? '',
    tag: props.clan?.tag ?? '',
    description: props.clan?.description?.en ?? '',
    country_code: props.clan?.country_code ?? '',
    accepts_applications: props.clan?.accepts_applications ?? true,
});

function saveProfile(): void {
    if (!props.clan) return;
    profileForm.patch(route('my-clan.profile.update', props.clan.slug));
}

// ---------------------------------------------------------------------------
// Members tab — role update + inline remove confirm
// ---------------------------------------------------------------------------
const confirmingRemoveId = ref<string | null>(null);

function handleChangeRole(membershipId: string, newRole: string): void {
    router.patch(
        route('my-clan.members.role', membershipId),
        { role: newRole },
        { preserveScroll: true },
    );
}

function handleRemove(membershipId: string): void {
    confirmingRemoveId.value = membershipId;
}

function confirmRemove(membershipId: string): void {
    router.delete(route('my-clan.members.remove', membershipId), {
        preserveScroll: true,
        onSuccess: () => { confirmingRemoveId.value = null; },
    });
}

function cancelRemove(): void {
    confirmingRemoveId.value = null;
}

// ---------------------------------------------------------------------------
// Invite modal
// ---------------------------------------------------------------------------
const inviteModalOpen = ref(false);
const inviteForm = useForm({
    invited_username: '',
    message: '',
});

function sendInvite(): void {
    inviteForm.post(route('my-clan.invites.store'), {
        onSuccess: () => {
            inviteModalOpen.value = false;
            inviteForm.reset();
        },
    });
}

// ---------------------------------------------------------------------------
// Invites tab — revoke
// ---------------------------------------------------------------------------
function revokeInvite(inviteId: string): void {
    router.delete(route('my-clan.invites.destroy', inviteId), {
        preserveScroll: true,
    });
}

// ---------------------------------------------------------------------------
// Received invites — invitee accept / decline (the only in-product entry point
// to act on an invite addressed to you)
// ---------------------------------------------------------------------------
function acceptInvite(inviteId: string): void {
    router.post(route('invites.accept', inviteId), {}, { preserveScroll: true });
}

function declineInvite(inviteId: string): void {
    router.post(route('invites.decline', inviteId), {}, { preserveScroll: true });
}

// Withdraw the applicant's own pending application (the only in-product entry
// point to cancel an application).
function withdrawApplication(applicationId: string): void {
    router.post(route('applications.cancel', applicationId), {}, { preserveScroll: true });
}

// ---------------------------------------------------------------------------
// Applications tab — accept / decline
// ---------------------------------------------------------------------------
function acceptApplication(appId: string): void {
    router.post(
        route('my-clan.applications.accept', appId),
        {},
        { preserveScroll: true },
    );
}

function declineApplication(appId: string): void {
    router.post(
        route('my-clan.applications.decline', appId),
        {},
        { preserveScroll: true },
    );
}

// ---------------------------------------------------------------------------
// Tab definitions
// ---------------------------------------------------------------------------
const tabs = [
    { value: 'profile', label: t('clans.my_clan.tab.profile') },
    { value: 'members', label: t('clans.my_clan.tab.members') },
    { value: 'invites', label: t('clans.my_clan.tab.invites') },
    { value: 'applications', label: t('clans.my_clan.tab.applications') },
];

function truncateMessage(msg: string | null, max = 120): string {
    if (!msg) return '';
    return msg.length > max ? msg.slice(0, max) + '…' : msg;
}
</script>

<template>
    <Head :title="t('clans.my_clan.title')" />

    <PublicLayout>
        <div class="max-w-3xl mx-auto px-4 md:px-6 py-8">

            <!-- ================================================================
                 Received invitations (invitee accept/decline) — shown in every
                 state so an unaffiliated player can act on an invite.
            ================================================================ -->
            <section
                v-if="received_invites.length"
                class="mb-8 border border-[var(--color-border)] rounded-lg p-5
                       bg-[var(--color-surface-elevated)]"
            >
                <h2 class="text-lg font-semibold text-[var(--color-text)] mb-4">
                    {{ t('clans.my_clan.received_invites.heading') }}
                </h2>
                <ul class="flex flex-col gap-3">
                    <li
                        v-for="invite in received_invites"
                        :key="invite.id"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between
                               border border-[var(--color-border)] rounded-md p-3
                               bg-[var(--color-surface)]"
                    >
                        <div class="flex flex-col gap-1 min-w-0">
                            <a
                                :href="`/clans/${invite.clan_slug}`"
                                class="font-semibold text-[var(--color-text)] hover:underline truncate"
                            >
                                [{{ invite.clan_tag }}] {{ invite.clan_name }}
                            </a>
                            <span class="text-xs text-[var(--color-text-muted)]">
                                {{ t('clans.my_clan.received_invites.invited_by', { inviter: invite.inviter_username ?? '—' }) }}
                            </span>
                            <p
                                v-if="invite.message"
                                class="text-sm text-[var(--color-text-muted)] mt-1"
                            >
                                {{ truncateMessage(invite.message) }}
                            </p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <Button
                                variant="primary"
                                @click="acceptInvite(invite.id)"
                            >
                                {{ t('clans.my_clan.received_invites.accept') }}
                            </Button>
                            <Button
                                variant="secondary"
                                @click="declineInvite(invite.id)"
                            >
                                {{ t('clans.my_clan.received_invites.decline') }}
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- ================================================================
                 Your pending applications (applicant withdraw) — shown in every
                 state so an applicant can cancel an application they submitted.
            ================================================================ -->
            <section
                v-if="my_applications.length"
                class="mb-8 border border-[var(--color-border)] rounded-lg p-5
                       bg-[var(--color-surface-elevated)]"
            >
                <h2 class="text-lg font-semibold text-[var(--color-text)] mb-4">
                    {{ t('clans.my_clan.my_applications.heading') }}
                </h2>
                <ul class="flex flex-col gap-3">
                    <li
                        v-for="application in my_applications"
                        :key="application.id"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between
                               border border-[var(--color-border)] rounded-md p-3
                               bg-[var(--color-surface)]"
                    >
                        <div class="flex flex-col gap-1 min-w-0">
                            <a
                                :href="`/clans/${application.clan_slug}`"
                                class="font-semibold text-[var(--color-text)] hover:underline truncate"
                            >
                                [{{ application.clan_tag }}] {{ application.clan_name }}
                            </a>
                            <span class="text-xs text-[var(--color-text-muted)]">
                                {{ t('clans.my_clan.my_applications.applied_to') }}
                            </span>
                            <p
                                v-if="application.message"
                                class="text-sm text-[var(--color-text-muted)] mt-1"
                            >
                                {{ truncateMessage(application.message) }}
                            </p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <Button
                                variant="secondary"
                                @click="withdrawApplication(application.id)"
                            >
                                {{ t('clans.my_clan.my_applications.withdraw') }}
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- ================================================================
                 No-clan state
            ================================================================ -->
            <template v-if="clan === null">
                <h1 class="text-2xl font-semibold text-[var(--color-text)] mb-2">
                    {{ t('clans.no_clan.title') }}
                </h1>
                <p class="text-[var(--color-text-muted)] mb-6">
                    {{ t('clans.no_clan.body') }}
                </p>

                <div class="flex flex-wrap gap-3 mb-8">
                    <a
                        href="/clans"
                        class="inline-flex items-center h-10 px-4 text-sm font-semibold rounded-md
                               bg-[var(--color-surface)] text-[var(--color-text)]
                               border border-[var(--color-border)]
                               hover:bg-[var(--color-surface-elevated)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]
                               transition-colors duration-[var(--motion-duration-fast)]"
                    >
                        {{ t('clans.no_clan.browse') }}
                    </a>
                </div>

                <!-- Inline create clan form -->
                <div
                    class="border border-[var(--color-border)] rounded-lg p-6
                           bg-[var(--color-surface-elevated)]"
                >
                    <h2 class="text-lg font-semibold text-[var(--color-text)] mb-4">
                        {{ t('clans.create.cta') }}
                    </h2>
                    <form class="flex flex-col gap-4" @submit.prevent="createClan">
                        <TextInput
                            id="create-name"
                            v-model="createForm.name"
                            :label="t('clans.form.name.label')"
                            required
                            :errors="createForm.errors.name ? [createForm.errors.name] : []"
                        />
                        <TextInput
                            id="create-tag"
                            v-model="createForm.tag"
                            :label="t('clans.form.tag.label')"
                            :placeholder="t('clans.form.tag.hint')"
                            required
                            :errors="createForm.errors.tag ? [createForm.errors.tag] : []"
                        />
                        <Textarea
                            id="create-description"
                            v-model="createForm.description"
                            :label="t('clans.form.description.label')"
                            :rows="3"
                            :errors="createForm.errors.description ? [createForm.errors.description] : []"
                        />
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                :disabled="createForm.processing"
                            >
                                {{ t('clans.create.cta') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </template>

            <!-- ================================================================
                 Clan management (Leader / Officer)
            ================================================================ -->
            <template v-else>
                <h1 class="text-2xl font-semibold text-[var(--color-text)] mb-8">
                    {{ t('clans.my_clan.title') }}
                </h1>

                <TabGroup :tabs="tabs">
                    <!-- ── Profile tab ──────────────────────────────────────── -->
                    <template #profile>
                        <form class="flex flex-col gap-5" @submit.prevent="saveProfile">
                            <TextInput
                                id="profile-name"
                                v-model="profileForm.name"
                                :label="t('clans.form.name.label')"
                                required
                                :errors="profileForm.errors.name ? [profileForm.errors.name] : []"
                            />
                            <TextInput
                                id="profile-tag"
                                v-model="profileForm.tag"
                                :label="t('clans.form.tag.label')"
                                :placeholder="t('clans.form.tag.hint')"
                                required
                                :errors="profileForm.errors.tag ? [profileForm.errors.tag] : []"
                            />
                            <Textarea
                                id="profile-description"
                                v-model="profileForm.description"
                                :label="t('clans.form.description.label')"
                                :rows="4"
                                :errors="profileForm.errors.description ? [profileForm.errors.description] : []"
                            />
                            <TextInput
                                id="profile-country"
                                v-model="profileForm.country_code"
                                :label="t('clans.form.country.label')"
                                :placeholder="'e.g. GB'"
                                :errors="profileForm.errors.country_code ? [profileForm.errors.country_code] : []"
                            />

                            <div class="flex flex-col gap-1">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input
                                        id="profile-accepts-applications"
                                        v-model="profileForm.accepts_applications"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border border-[var(--color-border)]
                                               bg-[var(--color-surface)] text-[var(--color-primary)]
                                               focus:outline-2 focus:outline-[var(--color-focus-ring)]"
                                    />
                                    <span class="text-sm font-medium text-[var(--color-text)]">
                                        {{ t('clans.form.accepts_applications.label') }}
                                    </span>
                                </label>
                                <p class="text-sm text-[var(--color-text-muted)] pl-6">
                                    {{ t('clans.form.accepts_applications.hint') }}
                                </p>
                            </div>

                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    :disabled="profileForm.processing"
                                >
                                    {{ t('clans.form.save') }}
                                </Button>
                            </div>
                        </form>
                    </template>

                    <!-- ── Members tab ─────────────────────────────────────── -->
                    <template #members>
                        <div class="flex justify-end mb-4">
                            <Button
                                variant="secondary"
                                @click="inviteModalOpen = true"
                            >
                                {{ t('clans.members.invite_button') }}
                            </Button>
                        </div>

                        <div class="border border-[var(--color-border)] rounded-lg overflow-hidden">
                            <MemberRow
                                v-for="m in members"
                                :key="m.id"
                                :member="m"
                                :show-actions="true"
                                @change-role="handleChangeRole"
                                @remove="handleRemove"
                            >
                                <template #actions="{ member }">
                                    <select
                                        :value="member.role"
                                        class="h-8 px-2 text-sm rounded-md border border-[var(--color-border)]
                                               bg-[var(--color-surface)] text-[var(--color-text)]
                                               focus:outline-2 focus:outline-[var(--color-focus-ring)]"
                                        :aria-label="t('clans.members.role.update.success')"
                                        @change="(e) => handleChangeRole(member.id, (e.target as HTMLSelectElement).value)"
                                    >
                                        <option value="leader">Leader</option>
                                        <option value="officer">Officer</option>
                                        <option value="member">Member</option>
                                        <option value="recruit">Recruit</option>
                                    </select>

                                    <!-- Inline remove confirmation -->
                                    <template v-if="confirmingRemoveId === member.id">
                                        <span class="text-sm text-[var(--color-text-muted)]">
                                            {{ t('clans.members.remove_confirm', { name: member.username ?? member.id }) }}
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="text-[var(--color-danger)]"
                                            @click="confirmRemove(member.id)"
                                        >
                                            {{ t('clans.members.remove_yes') }}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            @click="cancelRemove"
                                        >
                                            {{ t('common.actions.cancel') }}
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="text-[var(--color-danger)]"
                                            @click="handleRemove(member.id)"
                                        >
                                            {{ t('clans.members.remove_yes') }}
                                        </Button>
                                    </template>
                                </template>
                            </MemberRow>
                        </div>
                    </template>

                    <!-- ── Invites tab ─────────────────────────────────────── -->
                    <template #invites>
                        <div v-if="invites.length === 0" class="text-[var(--color-text-muted)] text-sm py-4">
                            {{ t('clans.invites.empty') }}
                        </div>

                        <div v-else class="flex flex-col gap-2">
                            <div
                                v-for="invite in invites"
                                :key="invite.id"
                                class="flex items-center gap-3 p-4 rounded-lg
                                       border border-[var(--color-border)]
                                       bg-[var(--color-surface)]"
                            >
                                <span class="flex-1 text-sm text-[var(--color-text)]">
                                    {{ invite.invited_user_id }}
                                </span>
                                <StatusBadge variant="pending" :label="invite.status" />
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="text-[var(--color-danger)]"
                                    @click="revokeInvite(invite.id)"
                                >
                                    {{ t('clans.invites.revoke') }}
                                </Button>
                            </div>
                        </div>
                    </template>

                    <!-- ── Applications tab ────────────────────────────────── -->
                    <template #applications>
                        <div v-if="applications.length === 0" class="text-[var(--color-text-muted)] text-sm py-4">
                            {{ t('clans.applications.empty') }}
                        </div>

                        <div v-else class="flex flex-col gap-2">
                            <div
                                v-for="app in applications"
                                :key="app.id"
                                class="flex items-start gap-3 p-4 rounded-lg
                                       border border-[var(--color-border)]
                                       bg-[var(--color-surface)]"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-[var(--color-text)]">
                                        {{ app.applicant_username ?? app.applicant_user_id }}
                                    </p>
                                    <p
                                        v-if="app.message"
                                        class="text-sm text-[var(--color-text-muted)] mt-1 truncate"
                                    >
                                        {{ truncateMessage(app.message) }}
                                    </p>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <Button
                                        size="sm"
                                        variant="primary"
                                        @click="acceptApplication(app.id)"
                                    >
                                        {{ t('clans.applications.accept') }}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="text-[var(--color-danger)]"
                                        @click="declineApplication(app.id)"
                                    >
                                        {{ t('clans.applications.decline') }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>
                </TabGroup>
            </template>

        </div>
    </PublicLayout>

    <!-- ===================================================================
         Invite member modal
    =================================================================== -->
    <Modal
        :open="inviteModalOpen"
        :title="t('clans.invites.modal_title')"
        @update:open="inviteModalOpen = $event"
    >
        <form class="flex flex-col gap-4" @submit.prevent="sendInvite">
            <TextInput
                id="invite-username"
                v-model="inviteForm.invited_username"
                :label="t('clans.invites.search_label')"
                required
                :errors="(inviteForm.errors as Record<string, string>).invited_user_id ? [(inviteForm.errors as Record<string, string>).invited_user_id] : []"
            />
            <Textarea
                id="invite-message"
                v-model="inviteForm.message"
                :label="t('clans.invites.message_label')"
                :placeholder="t('clans.invites.message_placeholder')"
                :rows="2"
                :errors="inviteForm.errors.message ? [inviteForm.errors.message] : []"
            />
            <div class="flex justify-end gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    @click="inviteModalOpen = false"
                >
                    {{ t('common.actions.cancel') }}
                </Button>
                <Button
                    type="submit"
                    variant="primary"
                    :disabled="inviteForm.processing"
                >
                    {{ t('clans.invites.send') }}
                </Button>
            </div>
        </form>
    </Modal>
</template>

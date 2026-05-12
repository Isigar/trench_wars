<!-- Source: 02-UI-SPEC.md § Page: /clans/{slug} (Public clan detail). -->
<script setup lang="ts">
import ClanRoleBadge from '@/components/clans/ClanRoleBadge.vue';
import ClanTagBadge from '@/components/clans/ClanTagBadge.vue';
import MemberRow from '@/components/clans/MemberRow.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useT } from '@/composables/useT';
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { computed } from 'vue';

const { t } = useT();

// Use generated DTO types from api.d.ts
type ClanData = App.Data.ClanData;
type ClanMembershipData = App.Data.ClanMembershipData;

const props = defineProps<{
    clan: ClanData;
    members: ClanMembershipData[];
    hiddenMemberCount: number;
}>();

// Clan status badge — only shown if NOT 'active' (UI-SPEC: active is normal state, no badge).
const showStatusBadge = computed(() => props.clan.status !== 'active');

// Avatar initials from clan name.
const initials = computed(() =>
    props.clan.name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);

// Member count label with pluralization.
const memberCountLabel = computed(() => {
    const count = props.clan.active_member_count;
    if (count === 1) return t('clans.members.count_one');
    return t('clans.members.count_other', { count });
});

// Privacy notice: partial = some hidden; all = all hidden.
const showPartialPrivacyNotice = computed(
    () => props.hiddenMemberCount > 0 && props.members.length > 0,
);
const showAllHiddenPrivacyNotice = computed(
    () => props.hiddenMemberCount > 0 && props.members.length === 0,
);
</script>

<template>
    <Head :title="clan.name" />

    <PublicLayout>
        <!-- Per-clan accent override hook (UI-SPEC § Design System § Per-clan accent override pattern).
             clan.accent_color is absent from the P2 DTO so :style is always {}, but the wrapper
             MUST exist for Phase 3+ to activate per-clan accent colors. -->
        <div :style="{}">
            <section class="max-w-3xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-8">

                <!-- Clan hero block -->
                <div class="flex flex-col sm:flex-row items-start gap-4">
                    <!-- Avatar 64×64 desktop / 56×56 mobile, rounded-lg (clan = lg; player = full) -->
                    <div
                        class="w-14 h-14 sm:w-16 sm:h-16 rounded-lg shrink-0
                               flex items-center justify-center
                               bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
                               text-xl font-semibold select-none"
                        aria-hidden="true"
                    >
                        {{ initials }}
                    </div>

                    <div class="flex flex-col gap-2">
                        <!-- H1 Display — clan name -->
                        <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                            {{ clan.name }}
                        </h1>

                        <!-- Tags inline -->
                        <div v-if="clan.tags && clan.tags.length" class="flex flex-wrap gap-2">
                            <ClanTagBadge
                                v-for="tag in clan.tags"
                                :key="tag.id"
                                :tag="tag"
                                as="span"
                            />
                        </div>

                        <!-- Member count + country -->
                        <div class="flex items-center gap-2 text-base text-[var(--color-text-muted)]">
                            <span>{{ memberCountLabel }}</span>
                            <template v-if="clan.country_code">
                                <span aria-hidden="true">·</span>
                                <span>{{ clan.country_code }}</span>
                            </template>
                        </div>

                        <!-- Status badge — only for non-active statuses -->
                        <StatusBadge
                            v-if="showStatusBadge"
                            :variant="(clan.status as 'active' | 'suspended' | 'disbanded')"
                        />
                    </div>
                </div>

                <!-- Description section — plain text, NO v-html (T-02-08-01). -->
                <div v-if="clan.description?.en" class="flex flex-col gap-2">
                    <p class="text-base text-[var(--color-text)] leading-relaxed whitespace-pre-wrap">
                        {{ clan.description.en }}
                    </p>
                </div>

                <!-- Members section -->
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('clans.section.members') }}
                    </h2>

                    <!-- Member rows -->
                    <div v-if="members.length > 0" class="rounded-lg overflow-hidden border border-[var(--color-border)]">
                        <MemberRow
                            v-for="member in members"
                            :key="member.id"
                            :member="member"
                            :show-actions="false"
                        />
                    </div>

                    <!-- Privacy notice: partial — some rows hidden -->
                    <p
                        v-if="showPartialPrivacyNotice"
                        class="text-sm text-[var(--color-text-muted)]"
                    >
                        {{ t('clans.privacy.roster_hidden_partial') }}
                    </p>

                    <!-- Privacy notice: all hidden -->
                    <p
                        v-if="showAllHiddenPrivacyNotice"
                        class="text-sm text-[var(--color-text-muted)]"
                    >
                        {{ t('clans.privacy.roster_hidden_all') }}
                    </p>
                </div>

                <!-- Recent activity placeholder -->
                <div class="flex flex-col gap-4">
                    <h2 class="text-xl font-semibold text-[var(--color-text)]">
                        {{ t('clans.section.recent_activity') }}
                    </h2>
                    <div class="bg-[var(--color-surface)] p-4 rounded-lg">
                        <p class="text-base text-[var(--color-text-muted)]">
                            {{ t('clans.activity.placeholder') }}
                        </p>
                    </div>
                </div>

            </section>
        </div>
    </PublicLayout>
</template>

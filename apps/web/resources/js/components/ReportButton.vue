<!-- Source: .planning/phases/09-polish/09-11-PLAN.md task 2.
     Inline "Report" CTA rendered on every public detail page (Clan/Show.vue,
     Player/Show.vue, Article/Show.vue, Match/Show.vue). v-if'd on auth state
     so anonymous visitors do not see a CTA that would redirect them to login
     before the form (T-09-11-04 — anonymous reports are NOT supported v1).

     Clicking deep-links to /reports/create?target_type=&target_id=&target_name=
     where ReportsController::create() pre-fills the form. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    targetType: string;
    targetId: string;
    targetName?: string;
}>();

const { t } = useT();
const page = usePage();

// Inertia shares the authenticated user under page.props.auth.user in the
// HandleInertiaRequests middleware (Phase 1 plan 01-09). Anonymous visitors
// see auth.user = null; we hide the CTA entirely in that case.
const isAuthenticated = computed<boolean>(() => {
    const auth = (page.props as { auth?: { user?: unknown } }).auth;
    return Boolean(auth?.user);
});

const reportUrl = computed<string>(() => {
    // Build query string manually — Ziggy's route() helper does not preserve
    // unknown query keys consistently across route definitions; an inline
    // URLSearchParams keeps the deep-link stable.
    const params = new URLSearchParams({
        target_type: props.targetType,
        target_id: props.targetId,
    });
    if (props.targetName !== undefined && props.targetName !== '') {
        params.set('target_name', props.targetName);
    }
    return `/reports/create?${params.toString()}`;
});
</script>

<template>
    <Link
        v-if="isAuthenticated"
        :href="reportUrl"
        class="inline-flex items-center gap-1 text-sm font-medium text-[var(--color-text-muted)]
               hover:text-[var(--color-text)]
               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]
               transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]"
        :aria-label="t('reports.cta.submit')"
    >
        <!-- Inline SVG flag icon (heroicons outline). Matches existing component
             pattern (icons in apps/web/resources/js/components/icons/). -->
        <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="w-4 h-4"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5"
            />
        </svg>
        <span>{{ t('reports.cta.submit') }}</span>
    </Link>
</template>

<!-- Source: .planning/phases/09-polish/09-11-PLAN.md task 2.
     SC-5 abuse-report submission page. Renders the form that POSTs to
     /reports (route name reports.store, throttle:report-abuse 5/hour by user).

     Props come from ReportsController::create() which reads them from the
     ?target_type=&target_id=&target_name= query string.

     i18n: every visible string flows through useT() per CLAUDE.md §7 (D-013).
     Keys defined in apps/web/lang/en/reports.php. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    target_type: string;
    target_id: string;
    target_name: string;
    reason_codes: string[];
}>();

const { t } = useT();

const form = reactive({
    target_type: props.target_type,
    target_id: props.target_id,
    reason_code: props.reason_codes[0] ?? 'other',
    body: '',
});

function submit(): void {
    router.post(route('reports.store'), { ...form }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('reports.page.title')" />

    <PublicLayout>
        <section class="max-w-2xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-6">
            <header class="flex flex-col gap-1">
                <h1
                    class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]"
                >
                    {{ t('reports.page.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ t('reports.page.description') }}
                </p>
            </header>

            <!-- Read-only target context — gives the reporter a visible confirmation of
                 what they are about to report, so they don't accidentally submit
                 against the wrong row. -->
            <div
                class="rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] p-4 flex flex-col gap-1"
            >
                <span
                    class="text-xs font-semibold uppercase tracking-wide text-[var(--color-text-muted)]"
                >
                    {{ t('reports.form.target_type') }}
                </span>
                <span class="text-sm text-[var(--color-text)]">
                    {{ form.target_type }} — {{ form.target_id }}
                </span>
                <span
                    v-if="props.target_name"
                    class="text-base font-semibold text-[var(--color-text)]"
                >
                    {{ props.target_name }}
                </span>
            </div>

            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium text-[var(--color-text)]">
                        {{ t('reports.form.reason_code') }}
                    </span>
                    <select
                        v-model="form.reason_code"
                        class="rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 text-base text-[var(--color-text)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        <option v-for="rc in props.reason_codes" :key="rc" :value="rc">
                            {{ t(`reports.reason_codes.${rc}`) }}
                        </option>
                    </select>
                </label>

                <label class="flex flex-col gap-2">
                    <span class="text-sm font-medium text-[var(--color-text)]">
                        {{ t('reports.form.body') }}
                    </span>
                    <textarea
                        v-model="form.body"
                        rows="6"
                        maxlength="2000"
                        :placeholder="t('reports.form.body_placeholder')"
                        class="rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 text-base text-[var(--color-text)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    />
                </label>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center font-semibold rounded-md
                               h-10 px-4 text-sm
                               bg-[var(--color-accent)] text-[var(--color-accent-fg)]
                               hover:opacity-90
                               transition-[opacity,background-color] duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                               focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
                    >
                        {{ t('reports.cta.submit') }}
                    </button>
                </div>
            </form>
        </section>
    </PublicLayout>
</template>

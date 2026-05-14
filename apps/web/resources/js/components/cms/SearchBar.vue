<!-- Source: 07-10-PLAN.md task 1 + must_haves.truths line 33 (SearchBar.vue —
     header search input, debounced submit on Enter via router.get('/search'),
     placeholder via t('search.header.q_placeholder')).

     Lives inside PublicLayout's nav slot (Phase 2 plan 02-08 idiom). Submits
     to GET /search?q= via Inertia router (preserves SPA navigation; no full
     page reload). 300ms debounce wraps router.get so users who keep typing
     after Enter do not double-issue requests.

     The data-test="search-bar" attribute is the smoke marker asserted by
     EventsCalendarPageTest's "header SearchBar present" it() block — proof
     that PublicLayout's extension renders on every public Inertia page. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const { t } = useT();

const q = ref<string>('');
let timer: ReturnType<typeof setTimeout> | null = null;

function submit(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
    const value = q.value.trim();
    if (value.length < 2) {
        // Below SearchRequest's min:2 — bounce silently to avoid 302 round-trip.
        return;
    }
    timer = setTimeout(() => {
        router.get('/search', { q: value }, { preserveScroll: true });
    }, 300);
}
</script>

<template>
    <form
        data-test="search-bar"
        role="search"
        class="flex items-center gap-1"
        @submit.prevent="submit"
    >
        <label class="sr-only" for="search-bar-q">{{ t('search.header.submit') }}</label>
        <input
            id="search-bar-q"
            v-model="q"
            type="search"
            autocomplete="off"
            :placeholder="t('search.header.q_placeholder')"
            class="px-2 py-1 text-sm rounded-md border border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text)] focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            @keydown.enter.prevent="submit"
        />
        <button
            type="submit"
            class="px-2 py-1 text-sm font-semibold rounded-md text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
        >
            {{ t('search.header.submit') }}
        </button>
    </form>
</template>

<!-- Source: 07-10-PLAN.md <interfaces> Events/Index.vue verbatim + Pattern 7
     (FullCalendar Vue3 mount) + Pitfall 11 (explicit local timezone).

     FullCalendar fetches /events/feed.json on every view change with start+end
     query params; the controller validates the range (max 90 days) + filters
     is_public=true (T-07-09-04 + T-07-09-07 mitigations from 07-09).

     eventClick uses Inertia router.visit (NOT window.location) so internal
     navigation stays SPA-style. -->
<script setup lang="ts">
import CalendarLegend from '@/components/cms/CalendarLegend.vue';
import { useT } from '@/composables/useT';
import PublicLayout from '@/layouts/PublicLayout.vue';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import FullCalendar from '@fullcalendar/vue3';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface CategoryRow {
    id: string;
    slug: string;
    name: string;
}

interface PageMeta {
    title: string;
    description: string;
}

const props = defineProps<{
    categories: CategoryRow[];
    meta: PageMeta;
}>();

const { t } = useT();

// Boolean view helper — extracted from template `v-if="x > 0"` style attribute
// expressions to keep the NoHardcodedStringsTest regex happy (it treats `>`
// inside attribute values as tag terminators).
const hasCategories = computed<boolean>(() => props.categories.length !== 0);

// FullCalendar Vue3 options. Typed `Record<string, unknown>` to avoid the
// strict CalendarOptions import (which would pin FC's internal types across
// the SSR boundary — not worth the friction for a 5-key config).
const calendarOptions = computed<Record<string, unknown>>(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    // Pitfall 11 — explicit timezone; ISO strings carry Z suffix per CalendarEventData.
    timeZone: 'local',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: '/events/feed.json',
    buttonText: {
        today: t('events.navigation.today'),
        month: t('events.header.month'),
        week: t('events.header.week'),
        day: t('events.header.day'),
    },
    height: 'auto',
    eventClick: (info: { jsEvent: Event; event: { url: string; extendedProps?: { url?: string } } }) => {
        // info.jsEvent.preventDefault() — block FC's default browser navigation
        // so Inertia router.visit owns the navigation (preserves SPA state).
        info.jsEvent.preventDefault();
        const target = info.event.extendedProps?.url ?? info.event.url;
        if (target !== '' && target !== undefined) {
            router.visit(target);
        }
    },
}));
</script>

<template>
    <Head :title="meta.title">
        <!-- Pitfall 4 mitigation: head-key dedupes across SPA navigation. -->
        <meta head-key="description" name="description" :content="meta.description" />
    </Head>

    <PublicLayout>
        <section class="max-w-5xl mx-auto px-4 md:px-6 py-8 flex flex-col gap-4" data-test="events-calendar">
            <header class="flex flex-col gap-2">
                <h1 class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]">
                    {{ t('events.header.title') }}
                </h1>
                <p class="text-base text-[var(--color-text-muted)]">
                    {{ meta.description }}
                </p>
            </header>

            <CalendarLegend />

            <div data-test="fullcalendar-mount">
                <FullCalendar :options="calendarOptions" />
            </div>

            <!-- Categories sidebar reserved for future filter chips — present in
                 the Inertia payload (assertable by EventsCalendarPageTest) so
                 the prop contract holds even before the filter UI lands. -->
            <ul v-if="hasCategories" class="hidden" aria-hidden="true" data-test="events-categories">
                <li v-for="category in categories" :key="category.id">{{ category.name }}</li>
            </ul>
        </section>
    </PublicLayout>
</template>

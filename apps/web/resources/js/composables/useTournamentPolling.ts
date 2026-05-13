// Source: 06-12-PLAN.md <interfaces> useTournamentPolling.ts + 06-RESEARCH.md
// Pattern 9 (30s polling + If-None-Match short-circuit).
//
// Polls /tournaments/{slug}.json every 30s, sending the previously-seen ETag in
// the If-None-Match header. On 304, the server short-circuits without re-serialising
// the payload and the local `tournament` ref stays untouched (T-06-12-01 mitigation).
// On 200, the ref is replaced with the fresh PublicTournamentData and the etag is
// recorded for the next tick. setInterval is cleared on unmount (T-06-12-05).
//
// Errors are caught + logged (we do not bubble to the user — a single failed poll
// must not break the UI; the next tick retries).

import { onMounted, onUnmounted, ref, type Ref } from 'vue';

type PublicTournamentData = App.Data.PublicTournamentData;

export const TOURNAMENT_POLL_INTERVAL_MS = 30_000;

export function useTournamentPolling(
    slug: string,
    tournament: Ref<PublicTournamentData>,
): void {
    const lastEtag = ref<string | null>(tournament.value.etag ?? null);
    let intervalId: ReturnType<typeof setInterval> | null = null;

    async function poll(): Promise<void> {
        try {
            const headers: Record<string, string> = {
                Accept: 'application/json',
            };
            if (lastEtag.value !== null) {
                headers['If-None-Match'] = `"${lastEtag.value}"`;
            }

            const res = await fetch(`/tournaments/${slug}.json`, {
                headers,
                credentials: 'same-origin',
            });

            // 304 Not Modified — server etag matches our last; nothing to do.
            if (res.status === 304) return;

            if (!res.ok) {
                // eslint-disable-next-line no-console
                console.error('[tournament-poll] non-OK status', res.status);
                return;
            }

            const body = (await res.json()) as {
                data: PublicTournamentData;
                etag: string;
                last_modified_at: string;
            };

            tournament.value = body.data;
            lastEtag.value = body.etag;
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('[tournament-poll] failed', e);
        }
    }

    onMounted(() => {
        intervalId = setInterval(() => {
            void poll();
        }, TOURNAMENT_POLL_INTERVAL_MS);
    });

    onUnmounted(() => {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
    });
}

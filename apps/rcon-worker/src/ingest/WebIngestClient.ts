// Plan 08-10 task 2 — replaces the Wave-0 skeleton (file did not exist).
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md <interfaces> + must_haves.
// Source (signing contract): apps/web/app/Support/Hmac/HmacVerifier.php + plan 08-05
//   VerifyRconSignature middleware. The body string we sign HERE is the exact same
//   body string we POST — never re-serialise (Pitfall 1 / T-08-10-01 mitigation).
//
// Threat ref T-08-10-01 (Tampering — body re-serialisation breaks HMAC):
//   `body = JSON.stringify({events})` is computed ONCE; the sign() output is over
//   THAT exact string; the POST sends THE SAME exact string. Any re-serialisation
//   (e.g. parsing + re-stringifying inside the verifier or this client) would
//   pick different key ordering and break hash_equals on the apps/web side.
//
// Plan 08-11 wires the BookingScheduler to call postEvents() in batches up to 25.
// Plan 08-12 SC-5 capstone is the cross-tier signature compatibility proof
// against the Laravel HmacVerifier — uses this client end-to-end.

import { fetch } from 'undici';
import type { Logger } from 'pino';
import { nonce, sign } from './HmacSigner.js';
import type { NormalisedEvent } from '../crcon/CrconEventNormaliser.js';

export interface WebIngestClientOptions {
    /** Shared HMAC secret — WEB_HMAC_SECRET env. ≥32 chars per config.ts. */
    secret: string;
    /** Web base URL — `http://web-nginx` in dev, `https://app.trenchwars.gg` in prod. */
    baseUrl: string;
    /** Pino logger (PII-redacting). */
    logger: Logger;
}

/** Result of a single postEvents() call — exposed so the caller can implement retry policy. */
export interface PostEventsResult {
    status: number;
    body: unknown;
}

/**
 * Signed batch POST client for the worker → web `/api/internal/match/{id}/events` route.
 *
 * Contract:
 *   POST {baseUrl}/api/internal/match/{matchId}/events
 *   Headers:
 *     Content-Type: application/json
 *     X-Rcon-Timestamp: {unix-ms-string}
 *     X-Rcon-Nonce: {uuid-v4}
 *     X-Rcon-Signature: {hex-hmac-sha256 over (timestamp + body)}
 *   Body: JSON.stringify({events: NormalisedEvent[]})  (≤25 events per call per plan)
 *
 * The Laravel side (App\Http\Middleware\VerifyRconSignature) re-computes the HMAC
 * over the raw request body bytes and hash_equals against the provided sig. Plan
 * 08-12 SC-5 verifies the cross-tier round-trip end-to-end.
 *
 * NOTE on retry / Redis fallback: this class is intentionally stateless re: retry.
 * The caller (plan 08-11 BookingScheduler) inspects the status code and pushes 5xx
 * batches into a Redis-backed drainer queue (08-11 task). This keeps the signing
 * concern + the queueing concern decoupled — easier to reason about and to test.
 */
export class WebIngestClient {
    constructor(private readonly opts: WebIngestClientOptions) {}

    /**
     * Sign + POST a batch of NormalisedEvent objects for a match.
     *
     * @param matchId UUID of the GameMatch (D-04-03-A LOCKED naming).
     * @param events  Normalised event batch (caller chunks to ≤25 per the plan).
     * @returns The HTTP status + parsed JSON body (or null on parse error).
     */
    async postEvents(matchId: string, events: NormalisedEvent[]): Promise<PostEventsResult> {
        // T-08-10-01 mitigation: build body ONCE, sign THAT string, POST THAT string.
        const body = JSON.stringify({ events });
        const timestamp = Date.now().toString();
        const signature = sign(this.opts.secret, body, timestamp);
        const url = `${this.opts.baseUrl}/api/internal/match/${matchId}/events`;

        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Rcon-Timestamp': timestamp,
                'X-Rcon-Nonce': nonce(),
                'X-Rcon-Signature': signature,
            },
            body,
        });

        const parsed = await resp.json().catch(() => null);
        return { status: resp.status, body: parsed };
    }

    /**
     * Plan 08-11 extension: HMAC-signed GET that returns parsed JSON.
     *
     * Used by BookingScheduler to poll `GET /api/internal/bookings/due` and by
     * MatchLifecycleManager to fetch `GET /api/internal/match-servers/{id}/credentials`.
     *
     * For GET the body is empty (""), so the digest input is `timestamp + ""` =
     * `timestamp` — the timestamp's uniqueness per request still defends against
     * replay (combined with the worker's X-Rcon-Nonce header). The Laravel-side
     * VerifyRconSignature middleware (plan 08-05) computes its expected digest
     * over `timestamp . raw_body` — for GET this is `timestamp . ""` which matches
     * the byte-for-byte signing we do here.
     *
     * Throws on non-2xx status or JSON parse failure so the caller can
     * differentiate transient failures (handled — logger.warn, no manager spawn)
     * from happy-path responses.
     */
    async fetchSignedJson<T>(path: string): Promise<T> {
        const body = '';
        const timestamp = Date.now().toString();
        const signature = sign(this.opts.secret, body, timestamp);
        const url = `${this.opts.baseUrl}${path}`;

        const resp = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Rcon-Timestamp': timestamp,
                'X-Rcon-Nonce': nonce(),
                'X-Rcon-Signature': signature,
            },
        });

        if (resp.status < 200 || resp.status >= 300) {
            throw new Error(`fetchSignedJson: ${path} returned status ${resp.status}`);
        }

        return (await resp.json()) as T;
    }
}

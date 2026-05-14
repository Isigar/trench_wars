// Plan 08-10 task 1 — replaces the Wave-0 skeleton from plan 08-01.
// Source: .planning/phases/08-rcon-automation/08-10-PLAN.md <interfaces>.
// Source (wire contract): 08-RESEARCH.md § HMAC Architecture → Wire Format.
//
// Cross-tier contract (D-021 LOCKED) — must match apps/web/app/Support/Hmac/HmacVerifier.php:
//   sign:    hash_hmac('sha256', $timestamp . $body, $secret)             — PHP (web side)
//   sign:    createHmac('sha256').update(timestamp + body).digest('hex')  — Node (this file)
//   verify:  hash_equals($expected, $providedSig)                         — PHP, constant-time
//   verify:  timingSafeEqual(Buffer.from(expected,'hex'), Buffer.from(given,'hex'))  — Node, constant-time
//
// Both sides:
//   - Reject empty secrets at sign() time (T-08-05-06 / mirror in the worker).
//   - Sign raw body bytes (Pitfall 1: re-serialising on the verification side picks a different
//     key order and breaks the signature). WebIngestClient.postEvents builds `body` ONCE via
//     JSON.stringify, signs THAT string, and POSTs the same string.
//
// Plan 08-12 SC-5 verifies this contract end-to-end against the Laravel HmacVerifier.

import { createHmac, randomUUID, timingSafeEqual } from 'node:crypto';

/**
 * Produce an HMAC-SHA256 signature in lowercase hex over `${timestamp}${body}`.
 *
 * @param secret    Shared HMAC secret (env WEB_HMAC_SECRET, ≥32 chars per config.ts).
 * @param body      Raw request body as sent on the wire. NEVER re-serialise — see Pitfall 1.
 * @param timestamp Stringified Unix-ms timestamp included in the X-Rcon-Timestamp header.
 * @returns Hex-encoded HMAC-SHA256 signature (lowercase).
 * @throws Error when secret is empty (mirror of T-08-05-06 fail-loud guard).
 */
export function sign(secret: string, body: string, timestamp: string): string {
    if (!secret) {
        throw new Error('HmacSigner: empty secret');
    }
    return createHmac('sha256', secret).update(timestamp + body).digest('hex');
}

/**
 * Constant-time verify of a signature against (timestamp + body) under secret.
 *
 * Returns false (rather than throwing) on any structural mismatch (unequal length,
 * non-hex input, etc.). Throws only when the secret itself is empty — that is a
 * deployment-time fault, not a per-request decision.
 */
export function verify(
    secret: string,
    body: string,
    timestamp: string,
    providedSig: string,
): boolean {
    const expected = sign(secret, body, timestamp);
    const a = Buffer.from(expected, 'hex');
    const b = Buffer.from(providedSig, 'hex');
    if (a.length === 0 || b.length === 0 || a.length !== b.length) {
        return false;
    }
    return timingSafeEqual(a, b);
}

/** Produce an RFC4122 v4 UUID for the X-Rcon-Nonce header (per-request replay defence). */
export function nonce(): string {
    return randomUUID();
}

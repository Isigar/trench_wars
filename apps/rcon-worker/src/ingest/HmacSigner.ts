// Wave 0 skeleton — Phase 8 plan 08-01 task 1.
// Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 1 behaviour list.
// Source (wire contract): 08-RESEARCH.md § HMAC Architecture → Wire Format + Worker signing.
//
// Contract:
//   sign(secret, body, timestamp)   → string  (HMAC-SHA256 hex over `${timestamp}.${body}`)
//   verify(secret, body, timestamp, signature) → boolean  (timing-safe equal)
//
// Both functions are STUBS in Wave 0 — they throw `Error('not implemented')` so the
// Vitest RED stub (tests/unit/HmacSigner.test.ts) and the worker→web verification
// middleware (plan 08-05) have a typed contract to handshake against. Real
// `node:crypto` implementation lands in plan 08-10 (worker side) and 08-05 (web side).

/**
 * Produce an HMAC-SHA256 signature in hex over `${timestamp}.${body}`.
 *
 * Sign the RAW request body bytes — see Pitfall 1 in 08-RESEARCH.md (re-serialising
 * JSON on the verification side picks a different key order and breaks the signature).
 *
 * @param _secret  Shared HMAC secret (min 32 chars per config.ts).
 * @param _body    Raw request body as sent on the wire (string or Buffer).
 * @param _timestamp  Unix ms timestamp included in the X-Rcon-Timestamp header.
 * @returns Hex-encoded HMAC-SHA256 signature.
 */
export function sign(_secret: string, _body: string | Buffer, _timestamp: number): string {
    throw new Error('HmacSigner.sign not implemented — replaced by plan 08-10');
}

/**
 * Constant-time verify of a signature against (timestamp + body) under secret.
 *
 * @param _secret    Shared HMAC secret.
 * @param _body      Raw body bytes received on the wire.
 * @param _timestamp Unix ms timestamp from X-Rcon-Timestamp header.
 * @param _signature Hex signature claimed by the sender.
 * @returns true iff signature matches AND timing-safe comparison succeeds.
 */
export function verify(
    _secret: string,
    _body: string | Buffer,
    _timestamp: number,
    _signature: string,
): boolean {
    throw new Error('HmacSigner.verify not implemented — replaced by plan 08-10/08-05');
}

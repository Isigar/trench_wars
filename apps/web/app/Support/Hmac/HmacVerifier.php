<?php

declare(strict_types=1);

namespace App\Support\Hmac;

use InvalidArgumentException;

/**
 * Stateless HMAC-SHA256 signer/verifier for the worker↔web internal channel.
 *
 * Source: 08-RESEARCH.md "HMAC Architecture" + CON-arch-rcon-to-web-comm contract.
 * Used by App\Http\Middleware\VerifyRconSignature (request gate) and re-usable
 * by Filament TestConnectionAction (plan 08-09) for parity probes.
 *
 * Signing input: `timestamp_string + raw_body_bytes`. NOT `(method + path + ...)`
 * — locked at CON-arch-rcon-to-web-comm to `(timestamp + body)` only.
 *
 * Threat refs (08-05 register):
 *  - T-08-05-02 (body tampering en route): `hash_equals()` constant-time compare
 *    on the canonical hex digest defeats early-exit timing oracles.
 *  - T-08-05-06 (empty secret deployed accidentally): `sign()` throws
 *    InvalidArgumentException when the secret is the empty string — fail-loud
 *    rather than fail-open (an empty-secret HMAC is still a stable digest,
 *    which would silently accept forgeries from any caller who guessed `''`).
 */
final class HmacVerifier
{
    /**
     * Compute the lowercase hex HMAC-SHA256 signature over `$timestamp . $body`.
     *
     * @throws InvalidArgumentException when the secret is the empty string.
     */
    public function sign(string $timestamp, string $body, string $secret): string
    {
        if ($secret === '') {
            throw new InvalidArgumentException(
                'HmacVerifier: refusing to sign with an empty WEB_HMAC_SECRET — '
                . 'set the env var or this gate would silently accept forgeries '
                . '(T-08-05-06).'
            );
        }

        return hash_hmac('sha256', $timestamp . $body, $secret);
    }

    /**
     * Constant-time compare against the provided signature.
     *
     * Returns false (not throws) when `$providedSig` is not a valid hex string
     * of the expected length — `hash_equals` requires equal-length operands.
     */
    public function verify(string $timestamp, string $body, string $providedSig, string $secret): bool
    {
        $expected = $this->sign($timestamp, $body, $secret);

        return hash_equals($expected, $providedSig);
    }
}

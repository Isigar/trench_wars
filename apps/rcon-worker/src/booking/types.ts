// Plan 08-11 task 1 — worker-side shape of the apps/web BookingDueData wire row.
// Source: .planning/phases/08-rcon-automation/08-11-PLAN.md <interfaces> +
//         apps/web/app/Data/Internal/BookingDueData.php (the producer).
//
// We define the shape locally in the worker rather than importing from
// @trenchwars/shared-types because plan 08-12 will regenerate that bundle and
// add BookingDueData; until then we honour the wire contract verbatim. The two
// shapes (PHP-side spatie/laravel-data DTO + worker-side interface) must match
// byte-for-byte — any future PHP-side rename MUST be matched here.
//
// Field rationale:
//  - id, match_id, server_id: UUID strings; carried through to per-match Redis
//    queue keys + CRCON session bookkeeping.
//  - server_host, server_port: pre-resolved from MatchServer relation server-side
//    (BookingDueData.fromModel). Worker still calls /api/internal/match-servers/{id}/credentials
//    to get the api_token — host/port arrive here for one-trip diagnostics.
//  - reserved_from / reserved_to: ISO-8601 strings (Carbon::toIso8601String()).
//    `new Date(reserved_to).getTime()` parses deterministically across runtimes.

export interface BookingDueData {
    id: string;
    match_id: string;
    server_id: string;
    server_host: string;
    server_port: number;
    /** ISO-8601 string. */
    reserved_from: string;
    /** ISO-8601 string. */
    reserved_to: string;
}

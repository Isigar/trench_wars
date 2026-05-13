// Trenchwars bot — match status to Discord embed color int.
//
// Source: .planning/phases/05-discord-bot-v1/05-08-PLAN.md task 2 (Wave 6).
// Consumed by plan 05-10 embed builders. Returns a 24-bit RGB integer suitable
// for `EmbedBuilder.setColor()`.
//
// Status set matches App\Models\GameMatch::STATUSES (Phase 4 plan 04-03):
//   draft     — being built in admin, not announced
//   open      — visible to players, sign-ups accepted (green)
//   locked    — sign-ups closed, rosters finalized (amber)
//   played    — completed, results recorded (blue)
//   cancelled — match called off (red)

export function statusColor(status: string): number {
    switch (status) {
        case 'open':
            return 0x00b050; // green
        case 'locked':
            return 0xf0a830; // amber
        case 'played':
            return 0x0078d4; // blue
        case 'cancelled':
            return 0xd13438; // red
        case 'draft':
        default:
            return 0x666666; // gray
    }
}

# 14-01 SUMMARY — Ban enforcement at the auth layer (REACH-04)

**Status:** ✅ Complete

`BanService::isCurrentlyBanned` + `User::activeBan` existed since plan 09-03 but had ZERO callers in
the request lifecycle — a banned user completed Discord OAuth and kept full authenticated access; the
ban was an audit record, not an access control. A dead docblock referenced a never-built "ban-check
middleware (plan 09-11)". Built it.

- `EnsureUserNotBanned` middleware — on a banned authenticated hit: log out + invalidate session +
  regenerate CSRF token, redirect home with an error flash. Aliased `banned`, mounted on the
  authenticated web route groups (`['auth', 'banned']` + the reports group).
- `DiscordController::callback` — login gate: a banned user is denied a session entirely (no
  login↔logout loop).
- `User::canAccessPanel` — a currently-banned admin loses Filament panel access too.
- Fixed the dead `User::activeBan` docblock (now names its real consumers).
- `auth.banned` i18n message.
- 4 feature tests: banned user denied + logged out, non-banned allowed, lifted ban does not block,
  banned admin loses panel access.

Gates: Pest (Auth + ban bulk-action + Security, 35), PHPStan L8, Pint — all green.

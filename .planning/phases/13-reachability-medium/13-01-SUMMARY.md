# 13-01 SUMMARY — Applicant withdraw-application UI (REACH-01)

**Status:** ✅ Complete

The applicant-cancel transition (`ClanApplicationService::cancel`, `POST /applications/{application}/cancel`) was fully implemented but had no UI — an applicant could never withdraw a mis-sent application. Added the missing surface, mirroring the v1.2 received-invites pattern.

- `MyClanApplicationData` DTO — applicant-facing projection (clan name/tag/slug + message); TS types regenerated.
- `MyClanController` computes the auth user's own pending applications (`my_applications`) and passes them in every rendered state.
- `MyClan/Index.vue` "Your pending applications" section with a Withdraw button hitting the existing `applications.cancel` route.
- `clans.my_clan.my_applications.*` i18n keys.
- 3 feature tests: surface renders with display fields, only pending shown, withdraw-from-surface cancels.

Gates: Pest (17 ClanApplicationTest), PHPStan L8, Pint, vue-tsc, NoHardcodedStrings — all green.

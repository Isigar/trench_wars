// Source: 01-RESEARCH.md HandleInertiaRequests::share() shape.
// `locale` + `translations` props added in plan 08; flat dot-keyed dictionary built
// server-side from `lang/en/*.php` and resolved by `useT()`.

// WR-03 (01-REVIEW.md): the previous shape wrapped the user inside an
// `auth.user` envelope that HandleInertiaRequests::share() never produces.
// The PHP side returns `$request->user()?->only([...])` — i.e., the user
// fields directly under `auth`, OR `null`. This declaration now matches.

export interface AuthUser {
    id: string;
    discord_id: string;
    username: string;
    avatar_url: string | null;
}

declare module '@inertiajs/core' {
    interface PageProps {
        auth: AuthUser | null;
        locale: string;
        translations: Record<string, string>;
        flash: {
            success: string | null;
            error: string | null;
        };
        ziggy: {
            url: string;
            port: number | null;
            defaults: Record<string, unknown>;
            routes: Record<string, unknown>;
            location: string;
        };
    }
}

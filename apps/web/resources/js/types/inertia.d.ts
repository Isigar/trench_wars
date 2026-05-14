// Source: 01-RESEARCH.md HandleInertiaRequests::share() shape.
// `locale` + `translations` props added in plan 08; flat dot-keyed dictionary built
// server-side from `lang/en/*.php` and resolved by `useT()`.

// WR-03 (01-REVIEW.md): the previous shape wrapped the user inside an
// `auth.user` envelope that HandleInertiaRequests::share() never produces.
// The PHP side returns `$request->user()?->only([...])` — i.e., the user
// fields directly under `auth`, OR `null`. This declaration now matches.

// Source: ZiggyVue plugin (tightenco/ziggy) registers `route()` globally on the Vue app.
// Must be inside `declare global {}` because this file has top-level exports (module context).
declare global {
    /** Global Ziggy route helper — provided by ZiggyVue plugin at runtime. */
    function route(name: string, params?: unknown, absolute?: boolean): string;
}

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
        /**
         * Plan 09-06 — unread DatabaseNotification count for the auth user.
         * `0` for guests (closure returns 0 when `auth()->user()` is null).
         * Rendered by NotificationsBell in PublicLayout (SC-1).
         */
        unread_notifications_count: number;
    }
}

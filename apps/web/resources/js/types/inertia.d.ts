// Source: 01-RESEARCH.md HandleInertiaRequests::share() shape.
// `locale` + `translations` props added in plan 08; flat dot-keyed dictionary built
// server-side from `lang/en/*.php` and resolved by `useT()`.

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: {
                id: string;
                discord_id: string;
                username: string;
                avatar_url: string | null;
            } | null;
        };
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

export {};

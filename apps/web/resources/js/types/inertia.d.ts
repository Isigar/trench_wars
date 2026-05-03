// Source: 01-RESEARCH.md HandleInertiaRequests::share() shape.
// `locale` + `translations` properties added in plan 08.

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

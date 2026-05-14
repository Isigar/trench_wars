<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

/**
 * Source: 01-RESEARCH.md Pattern 5 (i18n end-to-end via Inertia shared props).
 *
 * Plan 08 adds the `locale` and `translations` shared props on top of the
 * auth + flash + ziggy props from plan 06. The four UI namespaces declared in
 * `config/i18n.php` (`auth`, `common`, `admin`, `home`, `validation`) are loaded
 * via `trans()` and flat-merged into a dot-keyed dictionary so Vue's `t()` helper
 * can resolve `auth.discord.button_label` directly off `usePage().props.translations`.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => fn () => $request->user()?->only(['id', 'discord_id', 'username', 'avatar_url']),

            'locale' => fn () => app()->getLocale(),
            'translations' => fn () => $this->translations(app()->getLocale()),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],

            // Plan 09-06 — bell badge count rendered on every Inertia navigation
            // without an extra round-trip. Closure (lazy eval) so guests never
            // touch the notifications table. SC-1 (web bell + shared prop).
            'unread_notifications_count' => fn (): int => $request->user()?->unreadNotifications()->count() ?? 0,
        ]);
    }

    /**
     * Build the flat namespace→key dictionary from PHP lang files.
     *
     * Output shape: `{ 'auth.discord.button_label': 'Log in with Discord', ... }`.
     *
     * @return array<string, string>
     */
    protected function translations(string $locale): array
    {
        /** @var array<int, string> $namespaces */
        $namespaces = (array) config('i18n.shared_namespaces', ['auth', 'common', 'admin', 'home', 'validation']);

        $flat = [];
        foreach ($namespaces as $ns) {
            $values = trans($ns, [], $locale);
            if (is_array($values)) {
                $this->flatten($flat, $ns, $values);
            }
        }

        return $flat;
    }

    /**
     * Recursively flatten a nested translations array into a dot-keyed dictionary.
     *
     * @param  array<string, string>  $out
     * @param  array<string, mixed>  $values
     */
    protected function flatten(array &$out, string $prefix, array $values): void
    {
        foreach ($values as $key => $value) {
            $compositeKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $this->flatten($out, $compositeKey, $value);
            } else {
                $out[$compositeKey] = (string) $value;
            }
        }
    }
}

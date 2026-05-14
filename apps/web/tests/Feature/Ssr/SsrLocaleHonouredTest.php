<?php

declare(strict_types=1);

/*
| Source: .planning/phases/07-cms/07-11-PLAN.md task 2.
|
| Pitfall 8 mitigation — verify that the SSR HTML's <html lang="..."> attribute
| reflects the active app locale (app()->getLocale()) end-to-end, so the day a
| LocaleMiddleware lands (Phase 2+ may extend the resolver per config/i18n.php
| resolution_order = ['user','query','cookie','accept-language','default']),
| the SSR Node process inherits the resolved locale via Inertia shared props
| and the root Blade view (apps/web/resources/views/app.blade.php).
|
| Round-1 ships EN only (D-013, config('i18n.available_locales') = ['en']) so
| we exercise the chain with the available locale set + an explicit
| App::setLocale() pulse to prove the Blade resolution wiring. The third test
| temporarily extends available_locales so we can assert non-English routing
| works the day a CS / SK / PL pack drops (v2).
*/

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

it('renders <html lang="en"> for default Accept-Language (D-013 baseline)', function (): void {
    // Plan 01-06 set app.blade.php to <html lang="{{ str_replace('_','-', app()->getLocale()) }}">.
    // The default locale per .env.testing APP_LOCALE=en. Without any locale
    // override, the SSR HTML lang attribute MUST be "en".
    $response = $this->get('/');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="en"');
});

it('renders <html lang="cs"> when app locale is set to cs (Pitfall 8 chain)', function (): void {
    // Pitfall 8 — when (a future) LocaleMiddleware resolves Accept-Language: cs
    // and calls App::setLocale('cs'), app()->getLocale() must propagate into
    // the Blade root view BEFORE Inertia hands off to the SSR Node process.
    // We pulse setLocale() directly to prove the Blade resolution chain works
    // regardless of HOW the locale was set (cookie / query / header / middleware).
    Config::set('i18n.available_locales', ['en', 'cs']);
    App::setLocale('cs');

    $response = $this->get('/');
    $response->assertStatus(200);

    expect($response->getContent())->toContain('lang="cs"');
});

it('renders <html lang> matching the active locale even with underscored locales (en_US → en-US)', function (): void {
    // Plan 01-06 wraps app()->getLocale() in str_replace('_','-', ...) for the
    // BCP-47-compliant <html lang> attribute. Verify that translation of the
    // underscore to hyphen happens — accessibility tools require BCP-47.
    Config::set('i18n.available_locales', ['en', 'en_US']);
    App::setLocale('en_US');

    $response = $this->get('/');
    $response->assertStatus(200);

    expect($response->getContent())
        ->toContain('lang="en-US"')
        ->not->toContain('lang="en_US"');
});

it('exposes locale via Inertia shared props in lockstep with <html lang> (SSR↔client sync)', function (): void {
    // The SSR Node process sees `props.locale` from HandleInertiaRequests::share().
    // If the Blade lang attribute and the shared prop ever drift, the client
    // hydration would mismatch the SSR HTML. Lock them together here.
    Config::set('i18n.available_locales', ['en', 'cs']);
    App::setLocale('cs');

    $response = $this->get('/');
    $response->assertStatus(200);

    // <html lang="cs"> + Inertia data-page locale="cs" must coexist.
    $content = $response->getContent();
    expect($content)->toContain('lang="cs"');
    expect($content)->toContain('&quot;locale&quot;:&quot;cs&quot;');
});

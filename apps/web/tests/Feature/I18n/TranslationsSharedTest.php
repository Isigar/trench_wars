<?php

declare(strict_types=1);

/*
| Source: 01-VALIDATION.md (TranslationsSharedTest) + 01-RESEARCH.md Pattern 5.
|
| Asserts HandleInertiaRequests shares the `locale` and `translations` props on
| every request, that the locale is the resolved app locale, and that the
| translations dictionary contains the canonical UI-SPEC keys (the dictionary is
| flat dot-keyed so we cannot use `$page->where('translations.auth...')` — Inertia's
| AssertableInertia path resolver splits on `.` and would walk into the value).
| Instead we tap the props array and assert against the raw dictionary.
*/

use Inertia\Testing\AssertableInertia as Assert;

it('shares locale and translations on every Inertia response', function (): void {
    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('locale', 'en')
                ->has('translations')
        );
});

it('flat-merges every UI-SPEC namespace into the translations dictionary', function (): void {
    $this->get('/')
        ->assertInertia(function (Assert $page): void {
            $translations = $page->toArray()['props']['translations'] ?? [];

            expect($translations)
                ->toBeArray()
                ->toHaveKey('auth.discord.button_label')
                ->toHaveKey('home.tagline')
                ->toHaveKey('common.brand.name')
                ->toHaveKey('admin.audit.empty.heading')
                ->toHaveKey('validation.required');
        });
});

it('preserves UI-SPEC English copy verbatim', function (): void {
    $this->get('/')
        ->assertInertia(function (Assert $page): void {
            $translations = $page->toArray()['props']['translations'] ?? [];

            expect($translations['auth.discord.button_label'])->toBe('Log in with Discord');
            expect($translations['home.tagline'])->toBe('The league for clan-organised matches.');
            expect($translations['common.brand.name'])->toBe('Trenchwars');
        });
});

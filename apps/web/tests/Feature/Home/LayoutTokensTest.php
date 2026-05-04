<?php

declare(strict_types=1);

/*
| Source: 01-UI-SPEC.md § Definition of "Visually Correct" for Phase 1 Sign-Off.
| Asserts only what's automatable: HTML contains <html data-theme="dark">, the
| wordmark text "Trenchwars", and the @vite manifest references our app.css.
| Visual / contrast checks are manual per VALIDATION.md (Filament dual-Tailwind workaround).
*/

it('renders the public layout with dark default theme', function (): void {
    $response = $this->get('/');
    $response->assertStatus(200);
    expect($response->getContent())->toContain('data-theme="dark"');
    expect($response->getContent())->toContain('Trenchwars');
});

it('serves the css bundle in the manifest', function (): void {
    expect(file_exists(public_path('build/manifest.json')))->toBeTrue();
    $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
    expect($manifest)->toHaveKey('resources/css/app.css');
});

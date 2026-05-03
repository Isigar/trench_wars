<?php

declare(strict_types=1);

/*
| Source: 01-VALIDATION.md (InertiaSharedPropsTest stub) — partial coverage.
|
| This plan ships the auth + flash + ziggy props. Plan 08 will extend with
| translations + locale assertions; plan 09 with Discord OAuth login flow.
*/

use Inertia\Testing\AssertableInertia as Assert;

it('renders the Home page via Inertia', function (): void {
    $this->get('/')
        ->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Home')
                ->has('auth')
                ->has('flash')
                ->has('ziggy.routes')
        );
});

it('does not include a CSRF meta tag in the root view', function (): void {
    // Pitfall 3 mitigation: Inertia handles XSRF via cookie automatically.
    $response = $this->get('/');
    expect($response->getContent())->not->toContain('name="csrf-token"');
});

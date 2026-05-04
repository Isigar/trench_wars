<?php

declare(strict_types=1);

/*
| Source: 01-VALIDATION.md (ValidationMessagesLocalizedTest).
|
| Asserts validation messages resolve from `lang/en/validation.php` (in our custody)
| rather than the framework default. If lang/en/validation.php is missing or empty,
| `__('validation.required')` returns the literal key — that's the failure mode we
| guard against, since it would mean tomorrow's CS/SK locale drop has nothing to
| override.
*/

it('resolves validation.required from lang/en/validation.php', function (): void {
    $msg = __('validation.required', ['attribute' => 'foo']);

    expect($msg)->not->toBe('validation.required');
    expect($msg)->toContain('foo');
});

it('resolves validation.unique from lang/en/validation.php', function (): void {
    $msg = __('validation.unique', ['attribute' => 'username']);

    expect($msg)->not->toBe('validation.unique');
    expect($msg)->toContain('username');
});

it('resolves validation.email from lang/en/validation.php', function (): void {
    $msg = __('validation.email', ['attribute' => 'email address']);

    expect($msg)->not->toBe('validation.email');
    expect($msg)->toContain('email address');
});

<?php

declare(strict_types=1);

/*
| Wave 0 RED stub — replaced by plan 08-03 (MatchServer model w/ encrypted cast
| on password_encrypted column). Asserts that CRCON RCON credentials never land
| as plaintext in the DB and that decryption only happens via Laravel's
| Crypt::decryptString round-trip — matches SC-1 + security spec §6.
|
| Source: .planning/phases/08-rcon-automation/08-01-PLAN.md task 2.
*/

test('MatchServer.password_encrypted is stored ciphertext at rest and decrypts via Crypt cast', function (): void {
    expect(true)->toBeFalse();
});

<?php

declare(strict_types=1);

/**
 * Source: plan 01-15 verification — D-020 LOCKED. Asserts spatie/laravel-typescript-transformer
 * emits the api.d.ts file under resources/js/types/ and that the file mentions the three P1
 * DTO type aliases (UserData, PlayerData, PlayerPrivacyData).
 *
 * Cross-package sync to packages/shared-types/src/api.d.ts is exercised by the
 * trenchwars:typescript-generate command (depends on the docker-compose volume mount of
 * /repo/packages/shared-types/) — covered by the smoke test below as a best-effort assertion
 * (tolerant of CI environments where the host bind mount is absent).
 */

use Illuminate\Support\Facades\Artisan;

it('typescript:transform writes api.d.ts with all three P1 DTOs', function (): void {
    $output = resource_path('js/types/api.d.ts');

    @unlink($output);

    $exitCode = Artisan::call('typescript:transform');
    expect($exitCode)->toBe(0);

    expect(file_exists($output))->toBeTrue();

    $contents = (string) file_get_contents($output);
    expect($contents)->toContain('UserData');
    expect($contents)->toContain('PlayerData');
    expect($contents)->toContain('PlayerPrivacyData');
});

it('trenchwars:typescript-generate exits 0 and either syncs shared-types or warns gracefully', function (): void {
    $exitCode = Artisan::call('trenchwars:typescript-generate');
    expect($exitCode)->toBe(0);

    // Source api.d.ts must always exist after the command runs.
    expect(file_exists(resource_path('js/types/api.d.ts')))->toBeTrue();

    // If the /repo/packages/shared-types mount is present (local dev via docker-compose),
    // the synced file's contents match the source. Otherwise the command warns and exits 0.
    $sharedTypesTarget = '/repo/packages/shared-types/src/api.d.ts';
    if (is_dir(dirname($sharedTypesTarget))) {
        expect(file_exists($sharedTypesTarget))->toBeTrue();
        expect(file_get_contents($sharedTypesTarget))
            ->toBe(file_get_contents(resource_path('js/types/api.d.ts')));
    }
});

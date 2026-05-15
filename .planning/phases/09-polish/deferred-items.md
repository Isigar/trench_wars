# Phase 09 Polish — Deferred Items

Discovered during plan execution but outside the plan scope (per Rule 4 / scope boundary). Tracked here for future polish plans.

## From 09-08 (Wave 6 — Model::shouldBeStrict + N+1 sweep)

### Pre-existing PHP warnings (non-blocking, suite still GREEN)

- `tests/Feature/Services/BracketGeneratorSingleEliminationTest.php:13` — `use InvalidArgumentException` has no effect (PHP root namespace + alias).
- `tests/Feature/Services/BracketGeneratorSingleEliminationTest.php:14` — `use ReflectionMethod` has no effect (same).
- `tests/Feature/Services/BracketMatchMaterialiserServiceTest.php:18` — `use RuntimeException` has no effect (same).

These are PHP warnings, not errors. The tests pass and PHPStan does not analyse the `tests/` directory. Cleanup is a one-line removal per file but is unrelated to the strict-mode flip.

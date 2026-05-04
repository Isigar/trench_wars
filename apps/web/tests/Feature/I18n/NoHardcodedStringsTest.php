<?php

declare(strict_types=1);

/*
| Source: 01-VALIDATION.md (NoHardcodedStringsTest) + UI-SPEC.md Definition of
| "Visually Correct" #4 + D-013 ("Hardcoded strings are a CI failure").
|
| Greps the Vue templates of pages, layouts, and components for any English
| literal text node that is NOT inside a `{{ t(...) }}` / `{{ __() }}` mustache
| interpolation. We strip <script>/<style>/<!-- comment --> blocks and only check
| the `<template>` body. Attribute values are not scanned (would need a real Vue
| parser); use `:attr="t(...)"` for dynamic attrs and rely on `t()` review instead.
|
| Allowlist: empty in P1. Any literal English string flowing through a `<template>`
| body is a CI failure — author a `lang/en/<ns>.php` key and route through `t()`.
*/

it('contains no hardcoded English strings in pages, layouts, or components', function (): void {
    $roots = [
        base_path('resources/js/pages'),
        base_path('resources/js/layouts'),
        base_path('resources/js/components'),
    ];

    $offenders = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $dirIter = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $iter = new RecursiveIteratorIterator($dirIter);

        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $contents = (string) file_get_contents($file->getRealPath());

            // Extract the <template> body (stop at first </template>). We deliberately
            // ignore <script>/<style>/<!-- ... --> sections, which sometimes contain
            // English-looking variable names or developer comments.
            $templateOpen = strpos($contents, '<template>');
            if ($templateOpen === false) {
                continue;
            }
            $body = substr($contents, $templateOpen + strlen('<template>'));
            $templateClose = strpos($body, '</template>');
            if ($templateClose !== false) {
                $body = substr($body, 0, $templateClose);
            }

            // Strip HTML comments inside the template body.
            $body = (string) preg_replace('/<!--.*?-->/s', '', $body);

            // Capture text nodes between `>` and `<` of length >=3 (skips empty
            // tags and `< />` closers). Attribute values aren't matched by this
            // regex (they're inside `="..."` not between tags).
            preg_match_all('/>([^<]{3,})</', $body, $matches);

            foreach ($matches[1] as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                // Skip if there isn't a 3+ letter English run — pure punctuation,
                // numbers, or symbols (e.g. `&copy;`, `123`, `—`) are fine.
                if (! preg_match('/[A-Za-z]{3,}/', $candidate)) {
                    continue;
                }

                // Allowed: whole content is a Vue mustache interpolation
                // (possibly multi-mustache or with surrounding whitespace).
                $stripped = (string) preg_replace('/\\{\\{.*?\\}\\}/s', '', $candidate);
                $stripped = trim($stripped);
                if ($stripped === '' || ! preg_match('/[A-Za-z]{3,}/', $stripped)) {
                    continue;
                }

                $offenders[] = sprintf(
                    '%s :: %s',
                    str_replace(base_path() . '/', '', (string) $file->getRealPath()),
                    substr($candidate, 0, 80),
                );
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Hardcoded English strings found in Vue templates:\n  - " . implode("\n  - ", $offenders),
    );
});

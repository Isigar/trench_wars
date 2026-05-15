<?php

declare(strict_types=1);

/*
| Source: .planning/phases/09-polish/09-10-PLAN.md task 1 — turns the Wave 0
| stub from plan 09-01 GREEN.
|
| Covers SC-5 (WCAG 2.1 AA) — every interactive form control must have an
| accessible name. Static-template scan: for every <input>/<textarea>/<select>
| under apps/web/resources/js/{pages,layouts,components}, assert at least one of:
|
|   1. aria-label="…" or :aria-label="…"
|   2. aria-labelledby="…" or :aria-labelledby="…"
|   3. A wrapping or sibling <label for="X"> with id="X" on the control
|   4. A wrapping <label> that contains the control (implicit association)
|
| Exempted control types: hidden, submit, reset, button, image (button-shaped or
| not announced as form-fields by AT). Also exempted: <input> inside a <label>
| even when no for/id pair exists (implicit association is valid per HTML spec
| 4.10.4 — "any descendant of a label element").
|
| The scanner is regex-based (matches NoHardcodedStringsTest precedent — same
| pattern of strip-script-style-comment-then-grep-template). A full Vue AST
| parser would be overkill: every form control in the project is authored
| in the canonical Tailwind+Inertia style with the label either wrapping the
| input or appearing immediately before it with a matching for/id pair.
|
| Pre-existing violations (if any) MUST be fixed BEFORE this test passes —
| this is a hard SC-5 deliverable, not a future-flag.
*/

it('every Vue form input has an associated label or aria-label (static scan)', function (): void {
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

            // Extract the <template> body (stop at first </template>).
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

            $relativePath = str_replace(base_path() . '/', '', (string) $file->getRealPath());

            // Match <input ...>, <textarea ...>, <select ...> opening tags.
            // We use a non-greedy capture of the attribute blob and stop at the
            // first un-quoted `>`. Self-closing /> is naturally captured.
            //
            // CASE-SENSITIVE on purpose — only lowercase HTML elements are
            // AT-announced form controls. PascalCase Vue components (<Select>,
            // <TextInput>, <Textarea>) are audited at their wrapper definition
            // (apps/web/resources/js/components/ui/{Select,TextInput,Textarea}.vue
            // — all three emit `<label :for="id">` paired with the underlying
            // native control, so a parent passing `:label="..."` always yields
            // a labelled native element in the rendered DOM).
            preg_match_all(
                '/<(input|textarea|select)\b([^>]*)>/s',
                $body,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[0] as $i => $fullMatch) {
                $tagName = strtolower((string) $matches[1][$i][0]);
                $attrs = (string) $matches[2][$i][0];
                $offset = (int) $fullMatch[1];

                // Exempt non-AT-announced input types.
                if ($tagName === 'input') {
                    if (preg_match('/\btype\s*=\s*["\']?(hidden|submit|reset|button|image)["\']?/i', $attrs)) {
                        continue;
                    }
                }

                // Rule 1 + 2: aria-label / aria-labelledby (static or :bound).
                if (preg_match('/\b(:?aria-label|:?aria-labelledby)\s*=\s*["\']/i', $attrs)) {
                    continue;
                }

                // Rule 3: control has id="X" AND a <label for="X"> exists elsewhere
                // in the same template body.
                if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idMatch)) {
                    $id = preg_quote($idMatch[1], '/');
                    if (preg_match('/<label\b[^>]*\bfor\s*=\s*["\']' . $id . '["\']/i', $body)) {
                        continue;
                    }
                }
                // Rule 3 variant: :id="dynamicId" + <label :for="dynamicId">.
                if (preg_match('/\b:id\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idMatch)) {
                    $expr = preg_quote($idMatch[1], '/');
                    if (preg_match('/<label\b[^>]*\b:for\s*=\s*["\']' . $expr . '["\']/i', $body)) {
                        continue;
                    }
                }

                // Rule 4: control is implicitly inside a <label> wrapper. Walk
                // backwards from the control's offset; if the nearest open tag
                // boundary is <label and there is no intervening </label>, the
                // control is a descendant of that label.
                $before = substr($body, 0, $offset);
                $lastLabelOpen = strripos($before, '<label');
                $lastLabelClose = strripos($before, '</label>');
                if ($lastLabelOpen !== false && ($lastLabelClose === false || $lastLabelOpen > $lastLabelClose)) {
                    continue;
                }

                // No accessible-name path found — record the offender.
                $line = substr_count($body, "\n", 0, $offset) + 1;
                $excerpt = substr(trim((string) $fullMatch[0]), 0, 100);
                $offenders[] = sprintf('%s :: line ~%d :: %s', $relativePath, $line, $excerpt);
            }
        }
    }

    expect($offenders)->toBe(
        [],
        "Vue form inputs without an accessible name (label / aria-label):\n  - " . implode("\n  - ", $offenders),
    );
});

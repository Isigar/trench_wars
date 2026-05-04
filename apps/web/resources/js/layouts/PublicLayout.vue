<!-- Source: 01-UI-SPEC.md § Layout primitives + § Page chrome diagram.
     Plan 08 routes the skip-link label and footer copy through `t()` per D-013. -->
<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import Wordmark from '@/components/Wordmark.vue';
import { useT } from '@/composables/useT';

const { t } = useT();
const year = new Date().getFullYear();
</script>

<template>
    <a href="#main" class="skip-link">{{ t('common.actions.skip_to_content') }}</a>

    <div class="min-h-screen flex flex-col">
        <header
            class="border-b border-[var(--color-border)] bg-[var(--color-surface)]"
        >
            <div class="max-w-3xl mx-auto px-4 md:px-6 h-16 flex items-center justify-between gap-4">
                <Wordmark />

                <!-- Center nav slot — empty in P1, populated in Phase 2 (Clans, Players, Matches…) -->
                <nav class="hidden md:flex flex-1 justify-center">
                    <slot name="nav" />
                </nav>

                <div class="flex items-center gap-2">
                    <slot name="locale-switcher" />
                    <ThemeToggle />
                    <slot name="auth-action" />
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-[var(--color-border)] bg-[var(--color-surface)]">
            <div class="max-w-3xl mx-auto px-4 md:px-6 py-6 text-sm text-[var(--color-text-muted)]">
                <slot name="footer">
                    {{ `© ${year} ${t('common.brand.name')}` }}
                </slot>
            </div>
        </footer>
    </div>
</template>

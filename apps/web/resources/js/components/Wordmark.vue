<!-- Source: 01-UI-SPEC.md § Typography Display + § Copywriting Contract.
     Plan 08 routes the brand name through `t('common.brand.name')` so a future
     locale (CS/SK/PL) can localise the wordmark text without touching the file. -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import { Link } from '@inertiajs/vue3';

const { t } = useT();
</script>

<template>
    <Link
        href="/"
        class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]
               hover:text-[var(--color-accent)] transition-colors duration-[var(--motion-duration-fast)]"
    >
        {{ t('common.brand.name') }}
    </Link>
</template>

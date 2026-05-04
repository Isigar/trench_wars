<!-- Source: 01-UI-SPEC.md § Typography (Display 28px weight 600 tracking-tight; no SVG logo yet). -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <Link
        href="/"
        class="font-sans font-semibold text-[28px] leading-[1.2] tracking-tight text-[var(--color-text)]
               hover:text-[var(--color-accent)] transition-colors duration-[var(--motion-duration-fast)]"
    >
        Trenchwars
    </Link>
</template>

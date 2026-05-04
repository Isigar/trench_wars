<!-- Source: 01-UI-SPEC.md § ThemeToggle primitive + § Page chrome.
     Plan 08 routes the aria-label through `t()` per D-013. -->
<script setup lang="ts">
import IconButton from '@/components/ui/IconButton.vue';
import { useT } from '@/composables/useT';
import { useTheme } from '@/composables/useTheme';
import { Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';

const { theme, toggle } = useTheme();
const { t } = useT();
const label = computed(() =>
    theme.value === 'dark'
        ? t('common.theme.switch_to_light')
        : t('common.theme.switch_to_dark'),
);
</script>

<template>
    <IconButton :label="label" @click="toggle">
        <Moon v-if="theme === 'dark'" :size="20" />
        <Sun v-else :size="20" />
    </IconButton>
</template>

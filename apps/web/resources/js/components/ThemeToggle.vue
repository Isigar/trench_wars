<!-- Source: 01-UI-SPEC.md § ThemeToggle primitive + § Page chrome. -->
<script setup lang="ts">
import { computed } from 'vue';
import { Moon, Sun } from 'lucide-vue-next';
import IconButton from '@/components/ui/IconButton.vue';
import { useTheme } from '@/composables/useTheme';

const { theme, toggle } = useTheme();
const label = computed(() => (theme.value === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'));
</script>

<template>
    <IconButton :label="label" @click="toggle">
        <Moon v-if="theme === 'dark'" :size="20" />
        <Sun v-else :size="20" />
    </IconButton>
</template>

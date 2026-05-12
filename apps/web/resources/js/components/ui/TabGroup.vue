<!-- Source: 02-UI-SPEC.md § Component Inventory + § Page: /my-clan § Tab component. -->
<script setup lang="ts">
import {
    TabsContent,
    TabsList,
    TabsRoot,
    TabsTrigger,
} from 'reka-ui';

export interface Tab {
    value: string;
    label: string;
}

withDefaults(
    defineProps<{
        tabs: Tab[];
        defaultValue?: string;
    }>(),
    {
        defaultValue: undefined,
    },
);
</script>

<template>
    <TabsRoot :default-value="defaultValue ?? tabs[0]?.value">
        <!-- Tab bar — horizontal scroll on mobile per UI-SPEC responsive table -->
        <TabsList
            class="flex gap-1 border-b border-[var(--color-border)] overflow-x-auto"
            aria-label="Tabs"
        >
            <TabsTrigger
                v-for="tab in tabs"
                :key="tab.value"
                :value="tab.value"
                class="h-10 px-4 text-sm font-semibold text-[var(--color-text-muted)]
                       border-b-2 border-transparent -mb-px whitespace-nowrap
                       data-[state=active]:border-[var(--color-accent)]
                       data-[state=active]:text-[var(--color-text)]
                       transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                       focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            >
                {{ tab.label }}
            </TabsTrigger>
        </TabsList>

        <TabsContent
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
            class="pt-6 focus-visible:outline-none"
        >
            <slot :name="tab.value" />
        </TabsContent>
    </TabsRoot>
</template>

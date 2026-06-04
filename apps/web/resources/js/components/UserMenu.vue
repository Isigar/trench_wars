<!-- Source: 02-UI-SPEC.md § Component Inventory § UserMenu (Reka UI DropdownMenuRoot wrapper). -->
<script setup lang="ts">
import { useT } from '@/composables/useT';
import type { AuthUser } from '@/types/inertia';
import { router } from '@inertiajs/vue3';
import {
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuRoot,
    DropdownMenuTrigger,
} from 'reka-ui';
import { computed } from 'vue';

const { t } = useT();

const props = defineProps<{
    user: AuthUser;
}>();

// Avatar initials fallback from username.
const initials = computed(() =>
    props.user.username
        .split(/[^a-zA-Z0-9]+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase(),
);

function logout(): void {
    // Prefer Inertia router.post so the XSRF cookie flow is handled automatically.
    router.post('/auth/logout');
}
</script>

<template>
    <!-- Source: 02-UI-SPEC.md § UserMenu — avatar + username trigger, dropdown items. -->
    <DropdownMenuRoot>
        <DropdownMenuTrigger
            class="inline-flex items-center gap-2 h-9 px-2 rounded-md
                   text-sm font-semibold text-[var(--color-text)]
                   hover:bg-[var(--color-surface-elevated)]
                   transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]
                   focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]"
            :aria-label="user.username"
        >
            <!-- Avatar 32×32 or initials -->
            <img
                v-if="user.avatar_url"
                :src="user.avatar_url"
                :alt="user.username"
                class="w-8 h-8 rounded-full object-cover"
            />
            <div
                v-else
                class="w-8 h-8 rounded-full flex items-center justify-center
                       bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]
                       text-xs font-semibold select-none"
                aria-hidden="true"
            >
                {{ initials }}
            </div>
            <span class="hidden sm:inline">{{ user.username }}</span>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            :side-offset="4"
            class="z-50 min-w-[160px] rounded-md p-1
                   bg-[var(--color-surface-elevated)] border border-[var(--color-border)]
                   shadow-lg
                   data-[state=open]:animate-in data-[state=closed]:animate-out
                   data-[state=open]:fade-in data-[state=closed]:fade-out
                   duration-[var(--motion-duration-fast)]"
        >
            <!-- My Clan — plan 02-09 creates this route -->
            <DropdownMenuItem as-child>
                <a
                    href="/my-clan"
                    class="flex items-center gap-2 px-3 py-2 rounded-sm text-sm
                           text-[var(--color-text)] cursor-pointer
                           hover:bg-[var(--color-surface)] focus-visible:outline-none
                           data-[highlighted]:bg-[var(--color-surface)]"
                >
                    {{ t('common.nav.my_clan') }}
                </a>
            </DropdownMenuItem>

            <!-- My Profile — TODO Phase 9: wire to player slug when available -->
            <DropdownMenuItem as-child>
                <a
                    href="#"
                    class="flex items-center gap-2 px-3 py-2 rounded-sm text-sm
                           text-[var(--color-text)] cursor-pointer
                           hover:bg-[var(--color-surface)] focus-visible:outline-none
                           data-[highlighted]:bg-[var(--color-surface)]"
                    :title="t('common.nav.my_profile')"
                >
                    {{ t('common.nav.my_profile') }}
                </a>
            </DropdownMenuItem>

            <!-- Notification Preferences — plan 12-01 -->
            <DropdownMenuItem as-child>
                <a
                    href="/account/notification-preferences"
                    class="flex items-center gap-2 px-3 py-2 rounded-sm text-sm
                           text-[var(--color-text)] cursor-pointer
                           hover:bg-[var(--color-surface)] focus-visible:outline-none
                           data-[highlighted]:bg-[var(--color-surface)]"
                >
                    {{ t('common.nav.notification_preferences') }}
                </a>
            </DropdownMenuItem>

            <!-- Divider -->
            <div class="my-1 h-px bg-[var(--color-border)]" role="separator" />

            <!-- Log out -->
            <DropdownMenuItem
                class="flex items-center gap-2 px-3 py-2 rounded-sm text-sm
                       text-[var(--color-text)] cursor-pointer
                       hover:bg-[var(--color-surface)] focus-visible:outline-none
                       data-[highlighted]:bg-[var(--color-surface)]"
                @select="logout"
            >
                {{ t('common.actions.logout') }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenuRoot>
</template>

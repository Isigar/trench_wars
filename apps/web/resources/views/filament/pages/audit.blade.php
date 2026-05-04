{{--
    Source: 01-14-PLAN.md task 2 + 01-UI-SPEC.md § Page: /admin/audit.
    Empty-state heading + body keyed off admin.audit.empty.* (D-013 i18n).
--}}
<x-filament-panels::page>
    @if (\Spatie\Activitylog\Models\Activity::count() === 0)
        <div class="rounded-lg border border-dashed border-[var(--color-border)] p-12 text-center">
            <h2 class="text-xl font-semibold">
                {{ __('admin.audit.empty.heading') }}
            </h2>
            <p class="mt-2 text-[var(--color-text-muted)]">
                {{ __('admin.audit.empty.body') }}
            </p>
        </div>
    @else
        {{ $this->table }}
    @endif
</x-filament-panels::page>

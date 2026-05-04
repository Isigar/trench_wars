{{--
    Source: 01-14-PLAN.md task 2 — per-resource Audit tab partial.
    Renders the most recent 50 activity_log rows scoped to the current $subject
    Eloquent record. Read-only by design (no edit/delete on log rows — D-012).
--}}
@php
    /** @var \Illuminate\Database\Eloquent\Model|null $subject */
    // WR-08 (01-REVIEW.md): eager-load `causer` so the @foreach loop below does
    // not emit one extra `users` query per row. Otherwise rendering 50 rows
    // costs 51 queries.
    $activities = $subject
        ? \Spatie\Activitylog\Models\Activity::query()
            ->with('causer')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('id')
            ->limit(50)
            ->get()
        : collect();
@endphp

<div class="space-y-2">
    @forelse ($activities as $activity)
        <div class="rounded-md border border-[var(--color-border)] p-3 text-sm">
            <div class="flex justify-between gap-2">
                <span class="font-mono text-xs text-[var(--color-text-muted)]">
                    {{ $activity->created_at?->format('Y-m-d H:i:s') }}
                </span>
                <span class="text-xs font-semibold uppercase tracking-wide">
                    {{ $activity->event }}
                </span>
            </div>
            <div class="mt-1">
                {{ $activity->description }}
                @if ($activity->causer)
                    — {{ $activity->causer->username ?? $activity->causer->id }}
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-[var(--color-text-muted)]">
            {{ __('admin.audit.no_activity_yet') }}
        </p>
    @endforelse
</div>

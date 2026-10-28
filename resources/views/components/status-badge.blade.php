@props([
    'status',
    'label' => null,
])

@php
    /**
     * Renders one domain status with the shared vocabulary from app.scss.
     *
     * Statuses arrive from several modules ("Under Review", "On Leave",
     * "Non-Working Day"), so the value is slugged and matched against the
     * modifier list. An unmapped status still renders, styled as neutral, because
     * a new backend status must never produce an unstyled or missing badge.
     */
    $slug = \Illuminate\Support\Str::slug((string) $status);

    $aliases = [
        'under-review' => 'review',
        'in-review' => 'review',
        'in-progress' => 'processing',
        'early-departure' => 'late',
        'missing' => 'absent',
        'non-working-day' => 'non-working',
        'to-do' => 'draft',
        'done' => 'completed',
        'planning' => 'draft',
        'returned-for-changes' => 'returned',
    ];

    $known = [
        'draft', 'queued', 'pending', 'submitted', 'review', 'sending', 'processing',
        'approved', 'active', 'present', 'sent', 'paid', 'completed',
        'returned', 'late', 'incomplete', 'on-hold', 'on-leave',
        'rejected', 'failed', 'absent', 'suspended', 'cancelled', 'blocked',
        'inactive', 'holiday', 'non-working',
    ];

    $modifier = $aliases[$slug] ?? $slug;
    $modifier = in_array($modifier, $known, true) ? $modifier : 'draft';
@endphp

<span {{ $attributes->class(['status-badge', 'status-badge--' . $modifier]) }}>
    {{ $label ?? $status }}
</span>

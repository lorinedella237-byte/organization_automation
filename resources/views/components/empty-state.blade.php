@props([
    'icon' => 'inbox',
    'title' => null,
    'description' => null,
])

{{--
    Shown when a list has no rows. P7.04 requires every list to distinguish
    "nothing exists yet" from "your filters matched nothing". Pass a
    filter-aware title and description rather than reusing one generic message.

    `actions` slot holds the primary next step, when the viewer is permitted one.
--}}

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state__icon">
        <i class="bi bi-{{ $icon }} fs-4" aria-hidden="true"></i>
    </span>

    <p class="fw-semibold mb-1">{{ $title ?? __('Nothing to show') }}</p>

    @if ($description)
        <p class="text-secondary small mb-0 mx-auto" style="max-width: 32rem">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="d-flex justify-content-center gap-2 mt-3">{{ $actions }}</div>
    @endisset
</div>

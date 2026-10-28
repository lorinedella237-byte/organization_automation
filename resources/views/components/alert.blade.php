@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $icons = [
        'success' => 'check-circle-fill',
        'info' => 'info-circle-fill',
        'warning' => 'exclamation-triangle-fill',
        'danger' => 'exclamation-octagon-fill',
    ];

    // Errors and warnings must be announced to assistive technology; success
    // and info are polite so they do not interrupt.
    $assertive = in_array($type, ['danger', 'warning'], true);
@endphp

<div {{ $attributes->class([
        'alert d-flex gap-2',
        'alert-' . $type,
        'alert-dismissible' => $dismissible,
    ]) }}
     role="{{ $assertive ? 'alert' : 'status' }}"
     aria-live="{{ $assertive ? 'assertive' : 'polite' }}">

    <i class="bi bi-{{ $icons[$type] ?? $icons['info'] }} mt-1 flex-shrink-0" aria-hidden="true"></i>

    <div class="flex-grow-1">
        @if ($title)
            <p class="fw-semibold mb-1">{{ $title }}</p>
        @endif

        <div class="mb-0">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="{{ __('Dismiss') }}"></button>
    @endif
</div>

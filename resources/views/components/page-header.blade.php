@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

{{-- `actions` slot holds page-level buttons; omit it when there are none. --}}

<div {{ $attributes->class(['mb-4']) }}>
    @if ($breadcrumbs)
        <nav aria-label="{{ __('Breadcrumb') }}">
            <ol class="breadcrumb small mb-2">
                @foreach ($breadcrumbs as $label => $url)
                    @if ($loop->last || ! $url)
                        <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="h4 fw-semibold mb-1">{{ $title }}</h1>

            @if ($subtitle)
                <p class="text-secondary mb-0">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>

{{--
    Flash messages and the validation error summary.

    Included by both shells, so every page gets consistent feedback without
    repeating markup. Controllers flash `status`, `success`, `warning`, `info`
    or `error`.
--}}

@foreach (['success' => 'success', 'status' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'] as $key => $variant)
    @if (session()->has($key))
        <x-alert :type="$variant" dismissible>
            {{ session($key) }}
        </x-alert>
    @endif
@endforeach

{{-- Field-level messages are rendered beside their inputs; this is the summary. --}}
@if ($errors->any())
    <x-form-errors :errors="$errors" />
@endif

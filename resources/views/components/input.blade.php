@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'help' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);

    // Dotted names (address.city) and array names (roles[]) both need the bag
    // key without the brackets.
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($errorKey);

    $describedBy = array_filter([
        $help ? $id . '-help' : null,
        $hasError ? $id . '-error' : null,
    ]);
@endphp

<div {{ $attributes->only('class')->class(['mb-3']) }}>
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }}
            @if ($required)
                <span class="text-danger" aria-hidden="true">*</span>
                <span class="visually-hidden">({{ __('required') }})</span>
            @endif
        </label>
    @endif

    <input {{ $attributes->except('class')->merge([
                'type' => $type,
                'id' => $id,
                'name' => $name,
                'value' => old($errorKey, $value),
                'class' => 'form-control' . ($hasError ? ' is-invalid' : ''),
            ]) }}
           @if ($required) required aria-required="true" @endif
           @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
           @if ($hasError) aria-invalid="true" @endif>

    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif

    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}-error">
            {{ $errors->first($errorKey) }}
        </div>
    @endif
</div>

@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $hasError = $errors->has($errorKey);
    $current = old($errorKey, $selected);

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

    <select {{ $attributes->except('class')->merge([
                'id' => $id,
                'name' => $name,
                'class' => 'form-select' . ($hasError ? ' is-invalid' : ''),
            ]) }}
            @if ($required) required aria-required="true" @endif
            @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
            @if ($hasError) aria-invalid="true" @endif>

        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif

    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}-error">{{ $errors->first($errorKey) }}</div>
    @endif
</div>

@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 4,
    'help' => null,
    'required' => false,
])

@php
    $id = $attributes->get('id', $name);
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

    <textarea {{ $attributes->except('class')->merge([
                  'id' => $id,
                  'name' => $name,
                  'rows' => $rows,
                  'class' => 'form-control' . ($hasError ? ' is-invalid' : ''),
              ]) }}
              @if ($required) required aria-required="true" @endif
              @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
              @if ($hasError) aria-invalid="true" @endif>{{ old($errorKey, $value) }}</textarea>

    @if ($help)
        <div class="form-text" id="{{ $id }}-help">{{ $help }}</div>
    @endif

    @if ($hasError)
        <div class="invalid-feedback d-block" id="{{ $id }}-error">{{ $errors->first($errorKey) }}</div>
    @endif
</div>

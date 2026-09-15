@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'required' => false,
    'placeholder' => null,
    'options' => null,
    'picker' => null,
    'searchable' => false,
    'creatable' => false,
    'multiple' => false,
    'rows' => 3,
])

@php
    // `settings[fajr][volume]` must become `settings.fajr.volume` for old() and
    // for the error bag, which both use dot notation.
    $dotted = str_replace(['[', ']'], ['.', ''], $name);
    $current = old($dotted, $value);
    $hasError = $errors->has($dotted);
    $id = $attributes->get('id', 'field-'.str_replace('.', '-', $dotted));
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'mb-3']) }}>
    {{-- An empty label lets the caller render its own heading row above the
         field — used where a label sits beside a shortcut button. --}}
    @if (filled($label))
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if ($required)<span class="required-mark">*</span>@endif
        </label>
    @endif

    @if ($type === 'select')
        <select id="{{ $id }}"
                name="{{ $name }}{{ $multiple ? '[]' : '' }}"
                @class(['form-select', 'is-invalid' => $hasError])
                @if ($multiple) multiple @endif
                @if ($searchable) data-searchable data-placeholder="{{ $placeholder ?? 'Cari lalu pilih…' }}" @endif
                @if ($creatable) data-creatable @endif
                @if ($required) required @endif
                {{ $attributes->except(['class', 'id']) }}>

            @unless ($multiple)
                <option value="">{{ $placeholder ?? '— Pilih —' }}</option>
            @endunless

            @foreach ($options ?? [] as $optionValue => $optionLabel)
                <option value="{{ $optionValue }}"
                    @if ($multiple)
                        @selected(in_array($optionValue, (array) $current, false))
                    @else
                        @selected((string) $current === (string) $optionValue)
                    @endif>
                    {{ $optionLabel }}
                </option>
            @endforeach
        </select>

    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}"
                  name="{{ $name }}"
                  rows="{{ $rows }}"
                  @class(['form-control', 'is-invalid' => $hasError])
                  placeholder="{{ $placeholder }}"
                  @if ($required) required @endif
                  {{ $attributes->except(['class', 'id']) }}>{{ $current }}</textarea>

    @elseif ($type === 'checkbox')
        <div class="form-check form-switch">
            {{-- Paired hidden input so an unchecked box still submits a value. --}}
            <input type="hidden" name="{{ $name }}" value="0">
            <input type="checkbox"
                   id="{{ $id }}"
                   name="{{ $name }}"
                   value="1"
                   class="form-check-input"
                   @checked((bool) $current)
                   {{ $attributes->except(['class', 'id']) }}>
        </div>

    @else
        <input type="{{ $type }}"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ $current }}"
               @class(['form-control', 'is-invalid' => $hasError])
               placeholder="{{ $placeholder }}"
               @if ($picker) data-picker="{{ $picker }}" @endif
               @if ($required) required @endif
               {{ $attributes->except(['class', 'id']) }}>
    @endif

    @error($dotted)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror

    @if ($hint)
        <div class="form-hint">{{ $hint }}</div>
    @endif
</div>

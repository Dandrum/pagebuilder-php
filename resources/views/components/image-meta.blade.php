@props(['prefix', 'fields' => ['alt', 'caption', 'credit'], 'required' => [], 'readonly' => [], 'placeholders' => []])

{{-- Alt text, caption and photographer side by side (stacks in a narrow container). Read-only fields show a value instead of an input bound to the block. --}}
@php
    $labels = [
        'alt' => __('Alt text'),
        'caption' => __('Caption'),
        'credit' => __('Photographer / source'),
    ];
@endphp

<div class="pb-meta-row pb-meta-row--{{ count($fields) }}">
    @foreach($fields as $field)
        <div class="pb-field pb-field--{{ $field }}">
            <label class="pb-label">
                {{ $labels[$field] }}@if(in_array($field, $required, true)) <span class="pb-required" aria-hidden="true">*</span>@endif
                @if(array_key_exists($field, $readonly))
                    <input type="text" value="{{ $readonly[$field] }}" class="pb-input pb-input--readonly" readonly />
                @else
                    <input
                        type="text"
                        wire:model.blur="{{ $prefix.$field }}"
                        class="pb-input"
                        placeholder="{{ $placeholders[$field] ?? '' }}"
                        @if(in_array($field, $required, true)) required aria-required="true" @endif
                    />
                @endif
            </label>
            @error($prefix.$field)
                <span class="pb-error">{{ $message }}</span>
            @enderror
        </div>
    @endforeach
</div>

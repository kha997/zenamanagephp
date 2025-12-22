{{-- Form Controls Component --}}
{{-- Unified form inputs, buttons, and controls for consistent UI --}}

@props([
    'type' => 'text',
    'label' => null,
    'error' => null,
    'helperText' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'value' => null,
    'name' => null,
    'id' => null,
    'class' => '',
    'inputClass' => '',
    'labelClass' => '',
    'errorClass' => '',
    'helperClass' => ''
])

@php
    $inputId = $id ?? $name ?? uniqid('input_');
    $inputName = $name ?? '';
    $inputValue = $value ?? old($inputName);
    
    // Base classes
    $baseInputClass = 'block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 sm:text-sm transition-colors duration-200';
    $errorInputClass = 'border-red-300 focus:ring-red-500 focus:border-red-500';
    $disabledInputClass = 'bg-gray-50 text-gray-500 cursor-not-allowed';
    
    // Combine classes
    $finalInputClass = $baseInputClass;
    if ($error) {
        $finalInputClass .= ' ' . $errorInputClass;
    }
    if ($disabled) {
        $finalInputClass .= ' ' . $disabledInputClass;
    }
    if ($inputClass) {
        $finalInputClass .= ' ' . $inputClass;
    }
    
    $finalClass = $class ?: '';
@endphp

<div class="space-y-1 {{ $finalClass }}">
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-gray-700 {{ $labelClass }}">
            {{ $label }}
            @if($required)
                <span class="text-red-500 ml-1">*</span>
            @endif
        </label>
    @endif
    
    @if($type === 'textarea')
        <textarea
            id="{{ $inputId }}"
            name="{{ $inputName }}"
            rows="3"
            class="{{ $finalInputClass }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => '']) }}
        >{{ $inputValue }}</textarea>
    @elseif($type === 'select')
        <select
            id="{{ $inputId }}"
            name="{{ $inputName }}"
            class="{{ $finalInputClass }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => '']) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            {{ $slot }}
        </select>
    @else
        <input
            type="{{ $type }}"
            id="{{ $inputId }}"
            name="{{ $inputName }}"
            value="{{ $inputValue }}"
            class="{{ $finalInputClass }}"
            placeholder="{{ $placeholder }}"
            {{ $required ? 'required' : '' }}
            {{ $disabled ? 'disabled' : '' }}
            {{ $attributes->merge(['class' => '']) }}
        />
    @endif
    
    @if($error)
        <p class="text-sm text-red-600 {{ $errorClass }}">{{ $error }}</p>
    @elseif($helperText)
        <p class="text-sm text-gray-500 {{ $helperClass }}">{{ $helperText }}</p>
    @endif
</div>

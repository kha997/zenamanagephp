{{-- Standardized Form Input Component --}}
{{-- Reusable form input with consistent styling and validation --}}

@props([
    'name' => '',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'size' => 'md', // 'sm', 'md', 'lg'
    'variant' => 'default', // 'default', 'filled', 'outlined'
    'error' => null,
    'help' => null,
    'icon' => null,
    'iconPosition' => 'left', // 'left', 'right'
    'theme' => 'light'
])

@php
    $inputId = $name ?: 'input-' . uniqid();
    $hasError = !empty($error);
    $hasIcon = !empty($icon);
    
    $sizeClasses = [
        'sm' => 'px-3 py-2 text-sm',
        'md' => 'px-4 py-3 text-sm',
        'lg' => 'px-4 py-4 text-base'
    ];
    
    $inputClasses = [
        'form-input',
        $sizeClasses[$size],
        $variant === 'filled' ? 'filled' : '',
        $variant === 'outlined' ? 'outlined' : '',
        $hasError ? 'error' : '',
        $hasIcon ? 'with-icon' : '',
        $hasIcon && $iconPosition === 'right' ? 'icon-right' : ''
    ];
@endphp

<div class="form-group">
    {{-- Label --}}
    @if($label)
        <label for="{{ $inputId }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    {{-- Input Container --}}
    <div class="form-input-container">
        {{-- Left Icon --}}
        @if($hasIcon && $iconPosition === 'left')
            <div class="form-input-icon-left">
                <i class="{{ $icon }}"></i>
            </div>
        @endif
        
        {{-- Input Field --}}
        <input type="{{ $type }}"
               id="{{ $inputId }}"
               name="{{ $name }}"
               value="{{ old($name, $value) }}"
               @if($placeholder) placeholder="{{ $placeholder }}" @endif
               @if($required) required @endif
               @if($disabled) disabled @endif
               @if($readonly) readonly @endif
               @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               class="{{ implode(' ', array_filter($inputClasses)) }}"
               {{ $attributes }}>
        
        {{-- Right Icon --}}
        @if($hasIcon && $iconPosition === 'right')
            <div class="form-input-icon-right">
                <i class="{{ $icon }}"></i>
            </div>
        @endif
    </div>
    
    {{-- Error Message --}}
    @if($hasError)
        <div class="form-error">
            <i class="fas fa-exclamation-circle mr-1"></i>
            {{ $error }}
        </div>
    @endif
    
    {{-- Help Text --}}
    @if($help)
        <div class="form-help">
            {{ $help }}
        </div>
    @endif
</div>

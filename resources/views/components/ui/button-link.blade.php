@props([
    'href' => '#',
    'variant' => 'primary',
])

@php
    $variantClasses = match ($variant) {
        'secondary' => 'operator-button operator-button-secondary',
        'inline' => 'operator-button operator-button-inline',
        default => 'operator-button operator-button-primary',
    };
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => $variantClasses]) }}>
    {{ $slot }}
</a>

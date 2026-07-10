@props(['status'])

@php
    $isCalibrated = strtoupper((string) $status) === 'CALIBRATED';
    $classes = $isCalibrated ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white';
    $label = $isCalibrated ? '✓ Đã hiệu chỉnh' : '⚠ Chưa hiệu chỉnh';
@endphp

<span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $classes }}">
    {{ $label }}
</span>

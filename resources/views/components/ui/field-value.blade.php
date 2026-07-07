@props([
    'label',
    'value' => null,
])

<div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-sm font-medium text-slate-900">
        @if (filled($value))
            {{ $value }}
        @elseif (isset($slot) && $slot->isNotEmpty())
            {{ $slot }}
        @else
            —
        @endif
    </div>
</div>

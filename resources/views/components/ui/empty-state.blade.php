@props([
    'title',
    'description' => null,
])

<div class="operator-panel px-6 py-10 text-center">
    <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-2 max-w-2xl text-sm text-slate-500">{{ $description }}</p>
    @endif
    @if (trim((string) $slot) !== '')
        <div class="mt-5">
            {{ $slot }}
        </div>
    @endif
</div>

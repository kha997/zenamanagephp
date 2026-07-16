@props([
    'title',
    'description' => null,
])

<div class="mb-6 flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
    <div class="space-y-1">
        <h1 class="text-3xl font-semibold text-slate-900">{{ $title }}</h1>
        @if ($description)
            <p class="text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>

    @if (trim((string) $slot) !== '')
        <div class="flex items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>

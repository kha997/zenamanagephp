@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class(['operator-panel']) }}>
    @if ($title || $description)
        <div class="border-b border-slate-200 px-6 py-4">
            @if ($title)
                <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            @endif
        </div>
    @endif

    <div class="px-6 py-5">
        {{ $slot }}
    </div>
</section>

@props(['label' => 'Biểu mẫu ▾', 'links' => []])

@if (count($links) > 0)
    <details class="relative inline-block" data-template-dropdown>
        <summary class="inline-flex cursor-pointer select-none list-none items-center rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
            {{ $label }}
        </summary>
        <div class="absolute right-0 z-50 mt-1 w-56 rounded-md border border-slate-200 bg-white shadow-lg">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">{{ $link['label'] }}</a>
            @endforeach
        </div>
    </details>

    @once
        <script>
            document.addEventListener('click', function (event) {
                document.querySelectorAll('details[data-template-dropdown][open]').forEach(function (dropdown) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.removeAttribute('open');
                    }
                });
            });
        </script>
    @endonce
@endif

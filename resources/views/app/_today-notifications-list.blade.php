<ul class="space-y-2">
    @foreach ($items as $notification)
        <li class="text-sm">
            @if ($notification->url)
                <a href="{{ $notification->url }}" class="operator-link font-medium">{{ $notification->title }}</a>
            @else
                <span class="font-medium">{{ $notification->title }}</span>
            @endif
            <span class="text-slate-500"> — {{ $notification->createdAt->format('d/m/Y H:i') }}</span>
        </li>
    @endforeach
</ul>

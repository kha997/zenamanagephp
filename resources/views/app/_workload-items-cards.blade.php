{{-- GAP-118 C-1 / T-1: phone card list for "Việc của tôi"; tasks get a Start button (same form as the "Ghi giờ" card). --}}
<div class="operator-card-list">
    @foreach ($items as $item)
        <div class="operator-item-card" data-testid="work-item-card">
            <a href="{{ $item->url }}" class="operator-link operator-item-card-title">{{ $item->name }}</a>
            <div class="operator-item-card-meta">{{ $item->projectName }} · {{ $item->kindLabel }}</div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-slate-600">Hạn: {{ $item->endDate ? $item->endDate->format('d/m/Y') : '—' }}</span>
                @if ($item->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item->isBlocked)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item->status" />
                @endif
            </div>
            @if ($item->sourceType === 'task')
                <form method="POST" action="{{ route('app.time.start') }}" data-testid="task-card-start">
                    @csrf
                    <input type="hidden" name="task_id" value="{{ $item->sourceId }}">
                    <button type="submit" class="operator-button operator-button-primary operator-button-touch w-full">▶ Bắt đầu ghi giờ</button>
                </form>
            @endif
        </div>
    @endforeach
</div>

{{-- GAP-125 C-1: one card per NCR of this inspection below 768px. --}}
<div class="operator-card-list">
    @foreach ($ncrs as $ncr)
        @php
            $sevClasses = match ($ncr->severity) {
                'critical' => 'bg-rose-100 text-rose-800',
                'high' => 'bg-amber-100 text-amber-800',
                'medium' => 'bg-sky-100 text-sky-800',
                default => 'bg-slate-100 text-slate-600',
            };
            $sevLabel = match ($ncr->severity) {
                'critical' => 'Nghiêm trọng', 'high' => 'Cao', 'medium' => 'Trung bình', 'low' => 'Thấp', default => $ncr->severity,
            };
            $ncrStatusLabel = match ($ncr->status) {
                'open' => 'Đang mở', 'in_progress' => 'Đang xử lý', 'resolved' => 'Đã khắc phục', 'closed' => 'Đã đóng', default => $ncr->status,
            };
        @endphp
        <a href="{{ route('operator.inspections.ncrs.show', ['inspection' => $inspection->id, 'ncr' => $ncr->id]) }}" class="operator-item-card operator-item-card-link" data-testid="ncr-card">
            <span class="flex items-start justify-between gap-2">
                <span class="operator-item-card-title">{{ $ncr->ncr_number }} – {{ $ncr->title }}</span>
                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $sevClasses }}">{{ $sevLabel }}</span>
            </span>
            <span class="operator-item-card-meta">{{ $ncrStatusLabel }} · Giao cho {{ $ncr->assignee?->name ?? '—' }}</span>
        </a>
    @endforeach
</div>

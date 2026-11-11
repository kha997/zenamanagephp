{{-- GAP-125 C-1: one card per inspection below 768px. --}}
<div class="operator-card-list p-3">
    @foreach ($inspections as $inspection)
        @php
            $statusClasses = match ($inspection->status) {
                'scheduled' => 'bg-sky-100 text-sky-800',
                'in_progress' => 'bg-amber-100 text-amber-800',
                'completed' => 'bg-emerald-100 text-emerald-800',
                default => 'bg-slate-100 text-slate-700',
            };
            $statusLabel = match ($inspection->status) {
                'scheduled' => 'Đã lên lịch',
                'in_progress' => 'Đang thực hiện',
                'completed' => 'Hoàn tất',
                default => $inspection->status,
            };
        @endphp
        <div class="operator-item-card" data-testid="inspection-card">
            <div class="flex items-start justify-between gap-2">
                <a href="{{ route('operator.inspections.show', $inspection->id) }}" class="operator-item-card-title operator-link" data-testid="inspection-card-open">{{ $inspection->title }}</a>
                <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses }}">{{ $statusLabel }}</span>
            </div>
            <div class="operator-item-card-meta">{{ $inspection->qcPlan?->title ?? '—' }}</div>
            <div class="operator-item-card-meta">{{ $inspection->inspector?->name ?? '—' }} · {{ optional($inspection->inspection_date)->format('d/m/Y') ?? '—' }}</div>
        </div>
    @endforeach
</div>

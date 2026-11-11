{{-- GAP-125 C-1: one card per site diary below 768px; same details and submit / approve forms as the table. --}}
<div class="operator-card-list">
    @foreach ($siteDiaries as $siteDiary)
        <div class="operator-item-card" data-testid="site-diary-card">
            <div class="flex items-start justify-between gap-2">
                <a href="{{ route('operator.site-diaries.show', $siteDiary->id) }}" class="operator-item-card-title operator-link" data-testid="site-diary-card-open">
                    {{ optional($siteDiary->diary_date)->format('d/m/Y') }}
                </a>
                <x-ui.status-badge :status="$siteDiary->status" />
            </div>
            <div class="operator-item-card-meta">{{ $siteDiary->project?->name ?? '—' }}@if ($siteDiary->project?->code) · {{ $siteDiary->project->code }}@endif</div>
            @if ($siteDiary->work_performed)
                <div class="text-sm text-slate-700">{{ \Illuminate\Support\Str::limit((string) $siteDiary->work_performed, 140) }}</div>
            @endif
            <div class="operator-item-card-meta">{{ (int) $siteDiary->manpower_count }} người · Lập bởi {{ $siteDiary->creator?->name ?? '—' }}</div>
            @if ($siteDiary->status === 'draft')
                <form method="POST" action="{{ route('operator.site-diaries.submit', $siteDiary->id) }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary operator-button-touch">Gửi duyệt</button>
                </form>
            @elseif ($siteDiary->status === 'submitted')
                <form method="POST" action="{{ route('operator.site-diaries.approve', $siteDiary->id) }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary operator-button-touch">Phê duyệt</button>
                </form>
            @endif
        </div>
    @endforeach
</div>

@php /** @var array{state: string, days_late: int|null} $delay */ @endphp
@if ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_LATE)
    <span class="inline-flex items-center rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Trễ {{ $delay['days_late'] }} ngày</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_FORECAST_LATE)
    <span class="inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Dự kiến trễ {{ $delay['days_late'] }} ngày</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_ON_TRACK)
    <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Đúng tiến độ</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_NO_BASELINE)
    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Chưa chốt KH</span>
@endif
{{-- state 'completed': cố ý không render gì — badge status sẵn có đã nói đủ --}}

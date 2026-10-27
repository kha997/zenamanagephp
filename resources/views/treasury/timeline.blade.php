@extends('layouts.operator')

@section('title', 'Dòng thời gian ngân quỹ — ' . $project->name)
@section('page_title', 'Dòng thời gian tài chính')

@section('content')
    @php
        $linkTo = fn (?array $link) => match ($link['type'] ?? null) {
            'route' => route('operator.treasury.projects.routes.show', [$project->id, $link['id']]),
            'advance' => route('operator.treasury.projects.advances.show', [$project->id, $link['id']]),
            default => null,
        };
    @endphp

    <x-ui.page-header
        title="Dòng thời gian tài chính — {{ $project->name }}"
        description="Mọi khoản tiền của dự án trong tháng {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}, theo thứ tự thời gian."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <div class="flex flex-wrap items-center gap-2 p-4" data-testid="treasury-timeline-nav">
            <a href="{{ route('operator.treasury.projects.timeline', [$project->id, 'month' => $previousMonth]) }}" class="operator-button operator-button-inline">← Tháng trước</a>
            <span class="text-sm font-semibold">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}</span>
            <a href="{{ route('operator.treasury.projects.timeline', [$project->id, 'month' => $nextMonth]) }}" class="operator-button operator-button-inline">Tháng sau →</a>
        </div>
        @if ($events === [])
            <x-ui.empty-state title="Không có giao dịch trong tháng" description="Chọn tháng khác để xem." />
        @else
            <ol class="space-y-2 p-4">
                @foreach ($events as $event)
                    <li class="flex gap-3 text-sm" data-testid="treasury-timeline-event">
                        <span class="w-24 shrink-0 text-slate-500">{{ \Illuminate\Support\Carbon::parse($event['at'])->format('d/m/Y') }}</span>
                        <span>
                            {{ $event['sentence'] }}
                            @if ($url = $linkTo($event['link']))
                                <a href="{{ $url }}" class="ml-1 text-sky-700 underline">Xem</a>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-ui.card>
@endsection

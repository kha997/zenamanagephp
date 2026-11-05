@extends('layouts.operator')

@section('title', 'Việc của tôi')
@section('page_title', 'Việc của tôi')

@section('content')
    <x-ui.page-header
        title="Việc của tôi"
        description="Công việc và hạng mục thiết kế đang mở, gán cho bạn."
    />

    {{-- GAP-112 T-1: timer on own tasks. --}}
    @if ($running)
        <div class="mb-3 flex flex-wrap items-center gap-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm">
            <span>Đang ghi giờ: <strong>{{ $running->label() }}</strong> từ {{ $running->started_at?->format('H:i') }}</span>
            <form method="POST" action="{{ route('app.time.stop') }}" data-testid="time-stop-form">@csrf<button type="submit" class="operator-button operator-button-primary">Dừng</button></form>
            <a href="{{ route('app.time.index') }}" class="operator-link">Bảng giờ của tôi</a>
        </div>
    @endif

    <x-ui.card>
        <p class="mb-3 text-sm">
            <span class="font-medium text-slate-900">{{ $open_count }} đang mở</span>
            <span class="text-slate-400">·</span>
            <span class="{{ $overdue_count > 0 ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $overdue_count }} quá hạn</span>
            <span class="text-slate-400">·</span>
            <span class="{{ $blocked_count > 0 ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $blocked_count }} bị chặn</span>
        </p>

        @if ($items->isEmpty())
            <p class="text-sm text-slate-500">Bạn chưa có việc nào đang mở.</p>
        @else
            @include('app._workload-items-table', ['items' => $items])
        @endif
    </x-ui.card>

    @php $timedTasks = $items->where('sourceType', 'task'); @endphp
    @if ($timedTasks->isNotEmpty())
        <x-ui.card title="Ghi giờ">
            <div class="space-y-1 p-4">
                @foreach ($timedTasks as $item)
                    <form method="POST" action="{{ route('app.time.start') }}" class="flex items-center justify-between gap-2 border-b border-slate-100 py-1 text-sm" data-testid="time-start-form">
                        @csrf
                        <input type="hidden" name="task_id" value="{{ $item->sourceId }}">
                        <span>{{ $item->name }} <span class="text-xs text-slate-500">· {{ $item->projectName }}</span></span>
                        <button type="submit" class="operator-button operator-button-inline">Bắt đầu</button>
                    </form>
                @endforeach
            </div>
        </x-ui.card>
    @endif
@endsection

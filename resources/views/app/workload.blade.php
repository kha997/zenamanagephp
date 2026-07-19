@extends('layouts.operator')

@section('title', 'Khối lượng công việc')
@section('page_title', 'Khối lượng công việc')

@section('content')
    <x-ui.page-header
        title="Khối lượng công việc"
        description="Việc đang mở (công việc + hạng mục thiết kế) theo từng người, sắp theo tải giảm dần."
    />

    @php $totalOpen = collect($blocks)->sum('open_count') + count($unassigned); @endphp

    @if ($totalOpen === 0 && collect($blocks)->isEmpty())
        <x-ui.empty-state title="Không có việc nào đang mở" description="Mọi công việc và hạng mục thiết kế đều đã đóng." />
    @else
        @foreach ($blocks as $block)
            <x-ui.card :title="$block['user']->name">
                <p class="mb-2 text-sm">
                    <span class="font-medium text-slate-900">{{ $block['open_count'] }} đang mở</span>
                    <span class="text-slate-400">·</span>
                    <span class="{{ $block['overdue_count'] > 0 ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $block['overdue_count'] }} quá hạn</span>
                    <span class="text-slate-400">·</span>
                    <span class="{{ $block['blocked_count'] > 0 ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $block['blocked_count'] }} bị chặn</span>
                </p>
                @if ($block['items']->isEmpty())
                    <p class="text-sm text-slate-500">Không có việc đang mở.</p>
                @else
                    @include('app._workload-items-table', ['items' => $block['items']])
                @endif
            </x-ui.card>
        @endforeach

        @if (count($unassigned) > 0)
            <x-ui.card title="Chưa phân công">
                @include('app._workload-items-table', ['items' => $unassigned])
            </x-ui.card>
        @endif
    @endif
@endsection

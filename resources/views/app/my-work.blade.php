@extends('layouts.operator')

@section('title', 'Việc của tôi')
@section('page_title', 'Việc của tôi')

@section('content')
    <x-ui.page-header
        title="Việc của tôi"
        description="Công việc và hạng mục thiết kế đang mở, gán cho bạn."
    />

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
@endsection

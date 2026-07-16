@extends('layouts.operator')

@section('title', 'BOQ')
@section('page_title', 'BOQ — Bảng khối lượng')

@section('content')
    <x-ui.page-header
        title="BOQ"
        description="Quản lý bảng khối lượng (Bill of Quantities) theo dự án."
    >
        <x-ui.button-link :href="route('operator.boqs.create')">Tạo BOQ</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.boqs.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Mã, tên BOQ...">
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '')
                <a href="{{ route('operator.boqs.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($boqs->isEmpty())
            <x-ui.empty-state
                title="Chưa có BOQ"
                description="Tạo BOQ đầu tiên để quản lý khối lượng công việc theo dự án."
            >
                <x-ui.button-link :href="route('operator.boqs.create')">Tạo BOQ</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Mã', 'Tên', 'Dự án', 'Số hạng mục', 'Ngày tạo', 'Thao tác']">
                @foreach ($boqs as $boq)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $boq->code }}</td>
                        <td class="font-medium text-slate-900">{{ $boq->name }}</td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $boq->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $boq->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">{{ $boq->line_items_count }}</td>
                        <td class="text-sm text-slate-600">{{ optional($boq->created_at)->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('operator.boqs.show', $boq->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($boqs->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $boqs->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

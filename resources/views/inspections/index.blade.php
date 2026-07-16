@extends('layouts.operator')

@section('title', 'Kiểm định')
@section('page_title', 'Kiểm định chất lượng')

@section('content')
    <x-ui.page-header
        title="Kiểm định chất lượng"
        description="Lập lịch, thực hiện và hoàn tất các phiên kiểm định QC."
    >
        <x-ui.button-link :href="route('operator.inspections.create')">Tạo phiên kiểm định</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.inspections.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Tiêu đề...">
            </div>
            <div class="operator-field" style="max-width:200px">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="scheduled" @selected($currentStatus === 'scheduled')>Đã lên lịch</option>
                    <option value="in_progress" @selected($currentStatus === 'in_progress')>Đang thực hiện</option>
                    <option value="completed" @selected($currentStatus === 'completed')>Hoàn tất</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('operator.inspections.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($inspections->isEmpty())
            <x-ui.empty-state
                title="Chưa có phiên kiểm định"
                description="Tạo phiên kiểm định đầu tiên từ kế hoạch QC."
            >
                <x-ui.button-link :href="route('operator.inspections.create')">Tạo phiên kiểm định</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Tiêu đề', 'Kế hoạch QC', 'Người kiểm định', 'Ngày kiểm định', 'Trạng thái', 'Thao tác']">
                @foreach ($inspections as $inspection)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $inspection->title }}</td>
                        <td class="text-sm text-slate-600">{{ $inspection->qcPlan?->title ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $inspection->inspector?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($inspection->inspection_date)->format('d/m/Y') ?? '—' }}</td>
                        <td>
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
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses }}">{{ $statusLabel }}</span>
                        </td>
                        <td>
                            <a href="{{ route('operator.inspections.show', $inspection->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($inspections->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $inspections->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

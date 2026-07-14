@extends('layouts.operator')

@section('title', 'Hợp đồng')
@section('page_title', 'Hợp đồng')

@section('content')
    <x-ui.page-header
        title="Hợp đồng"
        description="Quản lý hợp đồng và theo dõi chi phí theo dự án."
    >
        <x-ui.button-link :href="route('operator.contracts.create')">Tạo hợp đồng</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.contracts.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Mã, tiêu đề...">
            </div>
            <div class="operator-field" style="max-width:200px">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="draft" @selected($currentStatus === 'draft')>Nháp</option>
                    <option value="active" @selected($currentStatus === 'active')>Đang hiệu lực</option>
                    <option value="closed" @selected($currentStatus === 'closed')>Đã đóng</option>
                    <option value="cancelled" @selected($currentStatus === 'cancelled')>Đã hủy</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('operator.contracts.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($contracts->isEmpty())
            <x-ui.empty-state
                title="Chưa có hợp đồng"
                description="Tạo hợp đồng đầu tiên để theo dõi giá trị và chi phí."
            >
                <x-ui.button-link :href="route('operator.contracts.create')">Tạo hợp đồng</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Mã', 'Tiêu đề', 'Dự án', 'Giá trị', 'Trạng thái', 'Hiệu lực', 'Thao tác']">
                @foreach ($contracts as $contract)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $contract->code }}</td>
                        <td class="font-medium text-slate-900">
                            {{ $contract->title }}
                            <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700">{{ $contract->typeLabel() }}</span>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $contract->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $contract->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ $contract->total_value !== null ? number_format((float) $contract->total_value) . ' ' . $contract->currency : '—' }}
                        </td>
                        <td>
                            @php
                                $statusClasses = match ($contract->status) {
                                    'active' => 'bg-emerald-100 text-emerald-800',
                                    'draft' => 'bg-slate-100 text-slate-700',
                                    'closed' => 'bg-slate-200 text-slate-600',
                                    'cancelled' => 'bg-rose-100 text-rose-800',
                                    default => 'bg-slate-100 text-slate-700',
                                };
                                $statusLabel = match ($contract->status) {
                                    'active' => 'Đang hiệu lực',
                                    'draft' => 'Nháp',
                                    'closed' => 'Đã đóng',
                                    'cancelled' => 'Đã hủy',
                                    default => $contract->status,
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusClasses }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ optional($contract->start_date)->format('d/m/Y') ?? '—' }} → {{ optional($contract->end_date)->format('d/m/Y') ?? '—' }}
                        </td>
                        <td>
                            <a href="{{ route('operator.contracts.show', $contract->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($contracts->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $contracts->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

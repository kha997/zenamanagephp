@extends('layouts.operator')

@section('title', 'RFI')
@section('page_title', 'RFI — Yêu cầu thông tin')

@section('content')
    <x-ui.page-header
        title="RFI"
        description="Tạo, theo dõi và phản hồi các yêu cầu thông tin (Request for Information)."
    >
        <x-ui.button-link :href="route('operator.rfis.create')">Tạo RFI</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.rfis.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Số RFI, tiêu đề...">
            </div>
            <div class="operator-field" style="max-width:200px">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="open" @selected($currentStatus === 'open')>Đang mở</option>
                    <option value="answered" @selected($currentStatus === 'answered')>Đã trả lời</option>
                    <option value="closed" @selected($currentStatus === 'closed')>Đã đóng</option>
                    <option value="escalated" @selected($currentStatus === 'escalated')>Đã chuyển cấp</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('operator.rfis.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($rfis->isEmpty())
            <x-ui.empty-state
                title="Chưa có RFI"
                description="Tạo RFI đầu tiên để bắt đầu luồng hỏi–đáp thông tin dự án."
            >
                <x-ui.button-link :href="route('operator.rfis.create')">Tạo RFI</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Số RFI', 'Tiêu đề', 'Dự án', 'Ưu tiên', 'Trạng thái', 'Hạn trả lời', 'Thao tác']">
                @foreach ($rfis as $rfi)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $rfi->rfi_number }}</td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $rfi->title ?? $rfi->subject }}</div>
                            <div class="text-sm text-slate-500">{{ $rfi->assignedTo?->name ? 'Giao cho: ' . $rfi->assignedTo->name : 'Chưa giao' }}</div>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $rfi->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $rfi->project?->code ?? '' }}</div>
                        </td>
                        <td>
                            @php
                                $priorityClasses = match ($rfi->priority) {
                                    'urgent' => 'bg-rose-100 text-rose-800',
                                    'high' => 'bg-amber-100 text-amber-800',
                                    'medium' => 'bg-sky-100 text-sky-800',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                                $priorityLabel = match ($rfi->priority) {
                                    'urgent' => 'Khẩn cấp',
                                    'high' => 'Cao',
                                    'medium' => 'Trung bình',
                                    'low' => 'Thấp',
                                    default => $rfi->priority,
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $priorityClasses }}">{{ $priorityLabel }}</span>
                        </td>
                        <td><x-ui.status-badge :status="$rfi->status" /></td>
                        <td class="text-sm text-slate-600">{{ optional($rfi->due_date)->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('operator.rfis.show', $rfi->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($rfis->hasPages())
                <div class="p-4 border-t border-gray-200">
                    {{ $rfis->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
@endsection

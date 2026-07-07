@extends('layouts.operator')

@section('title', 'Yêu cầu thay đổi')
@section('page_title', 'Yêu cầu thay đổi')

@section('content')
    <x-ui.page-header
        title="Yêu cầu thay đổi"
        description="Quản lý các thay đổi phạm vi, chi phí, tiến độ của dự án."
    >
        <x-ui.button-link :href="route('operator.change-requests.create')">Tạo yêu cầu</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.change-requests.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Số CR, tiêu đề...">
            </div>
            <div class="operator-field" style="max-width:200px">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="draft" @selected($currentStatus === 'draft')>Nháp</option>
                    <option value="submitted" @selected($currentStatus === 'submitted')>Đã gửi duyệt</option>
                    <option value="approved" @selected($currentStatus === 'approved')>Đã phê duyệt</option>
                    <option value="rejected" @selected($currentStatus === 'rejected')>Từ chối</option>
                    <option value="applied" @selected($currentStatus === 'applied')>Đã áp dụng</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('operator.change-requests.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($changeRequests->isEmpty())
            <x-ui.empty-state
                title="Chưa có yêu cầu thay đổi"
                description="Tạo yêu cầu thay đổi đầu tiên khi phạm vi, chi phí hoặc tiến độ cần điều chỉnh."
            >
                <x-ui.button-link :href="route('operator.change-requests.create')">Tạo yêu cầu</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Số CR', 'Tiêu đề', 'Dự án', 'Loại', 'Tác động chi phí', 'Trạng thái', 'Thao tác']">
                @foreach ($changeRequests as $changeRequest)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $changeRequest->change_number }}</td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $changeRequest->title }}</div>
                            <div class="text-sm text-slate-500">{{ $changeRequest->requestedBy?->name ?? '' }}</div>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $changeRequest->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $changeRequest->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ match($changeRequest->change_type) {
                                'scope' => 'Phạm vi',
                                'cost' => 'Chi phí',
                                'schedule' => 'Tiến độ',
                                'quality' => 'Chất lượng',
                                'design' => 'Thiết kế',
                                default => 'Khác',
                            } }}
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ $changeRequest->cost_impact !== null ? number_format((float) $changeRequest->cost_impact) : '—' }}
                        </td>
                        <td><x-ui.status-badge :status="$changeRequest->status" /></td>
                        <td>
                            <a href="{{ route('operator.change-requests.show', $changeRequest->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($changeRequests->hasPages())
                <div class="p-4 border-t border-gray-200">
                    {{ $changeRequests->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
@endsection

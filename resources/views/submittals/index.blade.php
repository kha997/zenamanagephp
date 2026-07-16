@extends('layouts.operator')

@section('title', 'Submittals')
@section('page_title', 'Submittals — Hồ sơ trình duyệt')

@section('content')
    <x-ui.page-header
        title="Submittals"
        description="Trình, xét duyệt hồ sơ shop drawing, mẫu vật liệu, tài liệu sản phẩm."
    >
        <x-ui.button-link :href="route('operator.submittals.create')">Tạo submittal</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.submittals.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Số submittal, tiêu đề, spec...">
            </div>
            <div class="operator-field" style="max-width:200px">
                <label for="status">Trạng thái</label>
                <select id="status" name="status" class="operator-select">
                    <option value="">Tất cả</option>
                    <option value="draft" @selected($currentStatus === 'draft')>Nháp</option>
                    <option value="submitted" @selected($currentStatus === 'submitted')>Đã gửi duyệt</option>
                    <option value="pending_review" @selected($currentStatus === 'pending_review')>Đang xét</option>
                    <option value="approved" @selected($currentStatus === 'approved')>Đã phê duyệt</option>
                    <option value="rejected" @selected($currentStatus === 'rejected')>Từ chối</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '' || $currentStatus !== '')
                <a href="{{ route('operator.submittals.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($submittals->isEmpty())
            <x-ui.empty-state
                title="Chưa có submittal"
                description="Tạo submittal đầu tiên để bắt đầu luồng trình duyệt hồ sơ."
            >
                <x-ui.button-link :href="route('operator.submittals.create')">Tạo submittal</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Số hồ sơ', 'Tiêu đề', 'Dự án', 'Loại', 'Trạng thái', 'Hạn duyệt', 'Thao tác']">
                @foreach ($submittals as $submittal)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $submittal->submittal_number }}</td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $submittal->title }}</div>
                            <div class="text-sm text-slate-500">{{ $submittal->specification_section ?? '' }}</div>
                        </td>
                        <td>
                            <div class="font-medium text-slate-900">{{ $submittal->project?->name ?? '—' }}</div>
                            <div class="text-sm text-slate-500">{{ $submittal->project?->code ?? '' }}</div>
                        </td>
                        <td class="text-sm text-slate-600">
                            @php
                                $typeLabel = match ($submittal->submittal_type) {
                                    'shop_drawing' => 'Shop drawing',
                                    'material_sample' => 'Mẫu vật liệu',
                                    'product_data' => 'Tài liệu sản phẩm',
                                    'test_report' => 'Báo cáo thí nghiệm',
                                    default => 'Khác',
                                };
                            @endphp
                            {{ $typeLabel }}
                        </td>
                        <td><x-ui.status-badge :status="$submittal->status" /></td>
                        <td class="text-sm text-slate-600">{{ optional($submittal->due_date)->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            <a href="{{ route('operator.submittals.show', $submittal->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($submittals->hasPages())
                <div class="p-4 border-t border-gray-200">
                    {{ $submittals->links() }}
                </div>
            @endif
        @endif
    </x-ui.card>
@endsection

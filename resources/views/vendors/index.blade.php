@extends('layouts.operator')

@section('title', 'Nhà cung cấp')
@section('page_title', 'Nhà cung cấp')

@section('content')
    <x-ui.page-header
        title="Nhà cung cấp"
        description="Danh mục nhà cung cấp vật tư và dịch vụ."
    >
        <x-ui.button-link :href="route('operator.vendors.create')">Thêm nhà cung cấp</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.vendors.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Tên, mã, người liên hệ...">
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '')
                <a href="{{ route('operator.vendors.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($vendors->isEmpty())
            <x-ui.empty-state
                title="Chưa có nhà cung cấp"
                description="Thêm nhà cung cấp đầu tiên để dùng trong hợp đồng và phiếu nhập."
            >
                <x-ui.button-link :href="route('operator.vendors.create')">Thêm nhà cung cấp</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Mã', 'Tên', 'Liên hệ', 'Email', 'Điện thoại', 'Trạng thái', 'Thao tác']">
                @foreach ($vendors as $vendor)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $vendor->code }}</td>
                        <td class="font-medium text-slate-900">{{ $vendor->name }}</td>
                        <td class="text-sm text-slate-600">{{ $vendor->contact_name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $vendor->email ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $vendor->phone ?? '—' }}</td>
                        <td>
                            @if ($vendor->is_active)
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">Hoạt động</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase text-slate-600">Ngưng</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('operator.vendors.show', $vendor->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($vendors->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $vendors->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

@extends('layouts.operator')

@section('title', 'Vật tư')
@section('page_title', 'Danh mục vật tư')

@section('content')
    <x-ui.page-header
        title="Danh mục vật tư"
        description="Quản lý danh mục vật tư dùng trong BOQ và phiếu nhập."
    >
        <x-ui.button-link :href="route('operator.materials.create')">Thêm vật tư</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.materials.index') }}" class="flex flex-wrap items-end gap-3 p-4 border-b border-gray-200">
            <div class="operator-field" style="max-width:280px">
                <label for="search">Tìm kiếm</label>
                <input id="search" name="search" type="text" class="operator-input" value="{{ $currentSearch }}" placeholder="Tên, mã, nhóm vật tư...">
            </div>
            <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
            @if ($currentSearch !== '')
                <a href="{{ route('operator.materials.index') }}" class="operator-button operator-button-inline">Xóa bộ lọc</a>
            @endif
        </form>

        @if ($materials->isEmpty())
            <x-ui.empty-state
                title="Chưa có vật tư"
                description="Thêm vật tư đầu tiên để dùng trong BOQ và phiếu nhập."
            >
                <x-ui.button-link :href="route('operator.materials.create')">Thêm vật tư</x-ui.button-link>
            </x-ui.empty-state>
        @else
            <x-ui.data-table :headers="['Mã', 'Tên', 'Nhóm', 'Đơn vị', 'Trạng thái', 'Thao tác']">
                @foreach ($materials as $material)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $material->code }}</td>
                        <td class="font-medium text-slate-900">{{ $material->name }}</td>
                        <td class="text-sm text-slate-600">{{ $material->category ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $material->unit ?? '—' }}</td>
                        <td>
                            @if ($material->is_active)
                                <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">Hoạt động</span>
                            @else
                                <span class="inline-flex rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold uppercase text-slate-600">Ngưng</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('operator.materials.show', $material->id) }}" class="operator-button operator-button-inline">Chi tiết</a>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>

            @if ($materials->hasPages())
                <div class="p-4 border-t border-gray-200">{{ $materials->links() }}</div>
            @endif
        @endif
    </x-ui.card>
@endsection

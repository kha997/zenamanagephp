@extends('layouts.operator')

@section('title', $material->name)
@section('page_title', $material->name)

@section('content')
    <x-ui.page-header
        :title="$material->name"
        :description="'Mã: ' . $material->code"
    >
        <x-ui.button-link :href="route('operator.materials.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin vật tư">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.field-value label="Mã" :value="$material->code" />
            <x-ui.field-value label="Tên" :value="$material->name" />
            <x-ui.field-value label="Nhóm" :value="$material->category" />
            <x-ui.field-value label="Đơn vị" :value="$material->unit" />
            <x-ui.field-value label="Trạng thái" :value="$material->is_active ? 'Hoạt động' : 'Ngưng'" />
            <x-ui.field-value label="Ngày tạo" :value="optional($material->created_at)->format('d/m/Y H:i')" />
        </div>
        @if ($material->description)
            <div class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $material->description }}</div>
        @endif
    </x-ui.card>
@endsection

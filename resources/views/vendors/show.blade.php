@extends('layouts.operator')

@section('title', $vendor->name)
@section('page_title', $vendor->name)

@section('content')
    <x-ui.page-header
        :title="$vendor->name"
        :description="'Mã: ' . $vendor->code"
    >
        <x-ui.button-link :href="route('operator.vendors.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin nhà cung cấp">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <x-ui.field-value label="Mã" :value="$vendor->code" />
            <x-ui.field-value label="Tên" :value="$vendor->name" />
            <x-ui.field-value label="Người liên hệ" :value="$vendor->contact_name" />
            <x-ui.field-value label="Email" :value="$vendor->email" />
            <x-ui.field-value label="Điện thoại" :value="$vendor->phone" />
            <x-ui.field-value label="Trạng thái" :value="$vendor->is_active ? 'Hoạt động' : 'Ngưng'" />
        </div>
        @if ($vendor->address)
            <div class="mt-4">
                <x-ui.field-value label="Địa chỉ" :value="$vendor->address" />
            </div>
        @endif
    </x-ui.card>
@endsection

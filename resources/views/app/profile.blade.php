@extends('layouts.operator')

@section('title', 'Hồ sơ cá nhân')
@section('page_title', 'Hồ sơ cá nhân')

@section('content')
    <x-ui.page-header
        title="Hồ sơ cá nhân"
        description="Thông tin tài khoản đang đăng nhập."
    />

    <x-ui.card title="Thông tin tài khoản">
        <div class="operator-form-grid">
            <x-ui.field-value label="Họ tên" :value="auth()->user()->name" />
            <x-ui.field-value label="Email" :value="auth()->user()->email" />
            <x-ui.field-value label="Vai trò" :value="auth()->user()->role ?? '—'" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="auth()->user()->is_active ? 'active' : 'inactive'" />
            </x-ui.field-value>
            <x-ui.field-value label="Tham gia" :value="optional(auth()->user()->created_at)->format('d/m/Y')" />
        </div>
    </x-ui.card>

    <x-ui.card title="Tích hợp">
        <p class="text-sm text-slate-600 mb-2">Quản lý API token cá nhân để tích hợp hệ thống ngoài.</p>
        <x-ui.button-link :href="route('operator.api-tokens.index')" variant="secondary">API Tokens</x-ui.button-link>
    </x-ui.card>
@endsection

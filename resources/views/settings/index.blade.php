@extends('layouts.operator')

@section('title', 'Cài đặt')
@section('page_title', 'Cài đặt')

@section('content')
    <x-ui.page-header
        title="Cài đặt"
        description="Cài đặt tài khoản và tích hợp. Cài đặt hệ thống nâng cao dùng API /api/v1/settings."
    />

    <div class="grid gap-6 md:grid-cols-2">
        <x-ui.card title="Tài khoản">
            <div class="space-y-3">
                <div>
                    <x-ui.button-link href="/app/profile" variant="secondary">Hồ sơ cá nhân</x-ui.button-link>
                </div>
                <p class="text-sm text-slate-500">Xem thông tin tài khoản, vai trò và trạng thái.</p>
            </div>
        </x-ui.card>

        <x-ui.card title="Tích hợp">
            <div class="flex flex-wrap gap-3">
                <x-ui.button-link :href="route('operator.api-tokens.index')" variant="secondary">API Tokens</x-ui.button-link>
                <x-ui.button-link :href="route('operator.webhooks.index')" variant="secondary">Webhooks</x-ui.button-link>
            </div>
            <p class="mt-3 text-sm text-slate-500">Token cá nhân và webhook đẩy sự kiện sang hệ thống ngoài.</p>
        </x-ui.card>
    </div>
@endsection

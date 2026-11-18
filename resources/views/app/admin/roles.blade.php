@extends('layouts.operator')

@section('title', 'Vai trò')
@section('page_title', 'Vai trò')

@section('content')
    {{-- GAP-129 R-1: the canonical roles, read-only. --}}
    <x-ui.page-header title="Vai trò" description="5 vai trò chuẩn và quyền của từng vai trò (chỉ xem). Đổi vai trò của một người ở trang Tài khoản." />

    <div class="grid gap-4">
        @foreach ($roles as $role)
            <x-ui.card :title="$role['name']">
                <div class="space-y-3 p-4 text-sm" data-role="{{ $role['name'] }}" data-permission-count="{{ $role['permissionCount'] }}">
                    <p>{{ $role['description'] }}</p>
                    <p class="text-slate-600">{{ $role['members'] }} người trong công ty · {{ $role['permissionCount'] }} quyền</p>
                    @if ($role['permissionCount'] > 0)
                        <details>
                            <summary class="cursor-pointer text-slate-700">Xem quyền theo nhóm</summary>
                            <dl class="mt-2 grid gap-2">
                                @foreach ($role['groups'] as $group => $codes)
                                    <div>
                                        <dt class="font-semibold">{{ $group }} ({{ count($codes) }})</dt>
                                        <dd class="text-slate-600">{{ collect($codes)->implode(', ') }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @else
                        <p class="text-amber-700">Chưa nạp quyền — chạy <code>php artisan production:rbac</code>.</p>
                    @endif
                </div>
            </x-ui.card>
        @endforeach
    </div>
@endsection

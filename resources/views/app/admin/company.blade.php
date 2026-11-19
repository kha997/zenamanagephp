@extends('layouts.operator')

@section('title', 'Công ty')
@section('page_title', 'Công ty')

@section('content')
    {{-- GAP-129 C-1: own company details; only the name is editable. --}}
    <x-ui.page-header title="Công ty" description="Thông tin công ty của bạn." />

    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    <x-ui.card title="Thông tin">
        <div class="space-y-4 p-4 text-sm">
            <dl class="grid max-w-xl grid-cols-2 gap-2">
                <dt class="text-slate-600">Mã công ty (slug)</dt><dd><code>{{ $tenant->slug }}</code></dd>
                <dt class="text-slate-600">Ngày tạo</dt><dd>{{ optional($tenant->created_at)->format('d/m/Y') }}</dd>
                <dt class="text-slate-600">Tài khoản</dt><dd>{{ $activeUsers }} đang hoạt động / {{ $totalUsers }}</dd>
            </dl>
            <form method="POST" action="{{ route('app.admin.company.update') }}" class="grid max-w-xl gap-3" data-testid="company-form">
                @csrf
                <label class="grid gap-1">Tên công ty
                    <input type="text" name="name" value="{{ old('name', $tenant->name) }}" class="operator-input" required maxlength="255">
                </label>
                <div><button type="submit" class="operator-button operator-button-primary">Lưu</button></div>
            </form>
            <p class="text-xs text-slate-500">Mã công ty không đổi được vì đang dùng trong link cổng khách hàng.</p>
        </div>
    </x-ui.card>
@endsection

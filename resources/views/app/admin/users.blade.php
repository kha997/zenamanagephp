@extends('layouts.operator')

@section('title', 'Tài khoản')
@section('page_title', 'Tài khoản')

@section('content')
    {{-- GAP-129 U-1: staff accounts of this company. --}}
    <x-ui.page-header title="Tài khoản" description="Tạo tài khoản, đổi vai trò, khoá / mở khoá và cấp lại mật khẩu cho nhân viên công ty." />

    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700" data-testid="admin-errors">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    @if ($otp = session('one_time_password'))
        <x-ui.card title="Mật khẩu một lần">
            <div class="space-y-2 p-4 text-sm" data-testid="one-time-password">
                <p>Mật khẩu cho <strong>{{ $otp['email'] }}</strong>:</p>
                <p><code class="select-all rounded bg-slate-100 px-2 py-1 text-base">{{ $otp['password'] }}</code></p>
                <p class="text-amber-700">Sao chép và gửi riêng cho người dùng ngay bây giờ. Mật khẩu sẽ <strong>không hiện lại</strong>.</p>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Tạo tài khoản">
        <form method="POST" action="{{ route('app.admin.users.store') }}" class="grid gap-3 p-4 text-sm md:grid-cols-4" data-testid="create-user-form">
            @csrf
            <label class="grid gap-1">Họ tên
                <input type="text" name="name" value="{{ old('name') }}" class="operator-input" required maxlength="255">
            </label>
            <label class="grid gap-1">Email đăng nhập
                <input type="email" name="email" value="{{ old('email') }}" class="operator-input" required maxlength="255" autocomplete="off">
            </label>
            <label class="grid gap-1">Vai trò
                <select name="role" class="operator-input" required>
                    @foreach ($roles as $name => $description)
                        <option value="{{ $name }}" @selected(old('role', 'Project Member') === $name)>{{ $description }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-end"><button type="submit" class="operator-button operator-button-primary">Tạo tài khoản</button></div>
        </form>
    </x-ui.card>

    <x-ui.card title="Danh sách ({{ $users->count() }})">
        <div class="operator-wide-only overflow-x-auto">
            <table class="operator-table text-sm" data-testid="users-table">
                <thead>
                    <tr><th>Họ tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr data-user-id="{{ $user->id }}">
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>@include('app.admin._role-form', ['user' => $user])</td>
                            <td>@include('app.admin._status', ['user' => $user])</td>
                            <td>{{ optional($user->created_at)->format('d/m/Y') }}</td>
                            <td>@include('app.admin._user-actions', ['user' => $user])</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="operator-phone-only">
            <div class="operator-card-list p-3">
                @foreach ($users as $user)
                    <div class="operator-item-card">
                        <div class="operator-item-card-title">{{ $user->name }}</div>
                        <div class="operator-item-card-meta">{{ $user->email }}</div>
                        <div>@include('app.admin._status', ['user' => $user])</div>
                        <div>@include('app.admin._role-form', ['user' => $user])</div>
                        <div>@include('app.admin._user-actions', ['user' => $user])</div>
                    </div>
                @endforeach
            </div>
        </div>
    </x-ui.card>
@endsection

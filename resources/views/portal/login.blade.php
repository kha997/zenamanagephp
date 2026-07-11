@extends('layouts.portal')

@section('title', 'Đăng nhập')

@section('content')
    <x-ui.card title="Đăng nhập">
        <p class="mb-4 text-sm text-slate-600">Nhập email đã đăng ký với chúng tôi để nhận liên kết đăng nhập.</p>
        <form method="POST" action="{{ route('portal.login.send', ['tenantSlug' => $tenant->slug]) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="operator-field flex-1 min-w-64">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="operator-input" value="{{ old('email') }}" required>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Gửi liên kết đăng nhập</button>
        </form>
        @error('email')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </x-ui.card>
@endsection

@extends('layouts.operator')

@section('title', 'Thêm nhà cung cấp')
@section('page_title', 'Thêm nhà cung cấp')

@section('content')
    <x-ui.page-header
        title="Thêm nhà cung cấp"
        description="Nhập thông tin nhà cung cấp mới."
    >
        <x-ui.button-link :href="route('operator.vendors.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin nhà cung cấp">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.vendors.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="code">Mã <span class="text-rose-600">*</span></label>
                    <input id="code" name="code" type="text" class="operator-input" value="{{ old('code', 'VENDOR-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4))) }}" maxlength="100" required placeholder="VD: VENDOR-001">
                    @error('code')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="name">Tên <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="contact_name">Người liên hệ</label>
                    <input id="contact_name" name="contact_name" type="text" class="operator-input" value="{{ old('contact_name') }}">
                </div>

                <div class="operator-field">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="operator-input" value="{{ old('email') }}">
                    @error('email')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="phone">Điện thoại</label>
                    <input id="phone" name="phone" type="text" class="operator-input" value="{{ old('phone') }}">
                </div>
            </div>

            <div class="operator-field">
                <label for="address">Địa chỉ</label>
                <textarea id="address" name="address" class="operator-textarea">{{ old('address') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Thêm nhà cung cấp</button>
        </form>
    </x-ui.card>
@endsection

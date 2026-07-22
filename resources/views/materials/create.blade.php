@extends('layouts.operator')

@section('title', 'Thêm vật tư')
@section('page_title', 'Thêm vật tư')

@section('content')
    <x-ui.page-header
        title="Thêm vật tư"
        description="Nhập thông tin vật tư mới vào danh mục."
    >
        <x-ui.button-link :href="route('operator.materials.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin vật tư">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.materials.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="code">Mã <span class="text-rose-600">*</span></label>
                    <input id="code" name="code" type="text" class="operator-input" value="{{ old('code', 'MAT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4))) }}" maxlength="100" required placeholder="VD: MAT-001">
                    @error('code')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="name">Tên <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="category">Nhóm vật tư</label>
                    <input id="category" name="category" type="text" class="operator-input" value="{{ old('category') }}" maxlength="100" placeholder="VD: Xi măng, Thép, Cát đá">
                </div>

                <div class="operator-field">
                    <label for="unit">Đơn vị</label>
                    <input id="unit" name="unit" type="text" class="operator-input" value="{{ old('unit') }}" maxlength="50" placeholder="VD: kg, m3, cái">
                </div>
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Thêm vật tư</button>
        </form>
    </x-ui.card>
@endsection

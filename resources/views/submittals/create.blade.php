@extends('layouts.operator')

@section('title', 'Tạo submittal')
@section('page_title', 'Tạo submittal')

@section('content')
    <x-ui.page-header
        title="Tạo submittal"
        description="Trình hồ sơ mới ở trạng thái nháp."
    >
        <x-ui.button-link :href="route('operator.submittals.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin submittal">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.submittals.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('project_id')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="submittal_type">Loại hồ sơ <span class="text-rose-600">*</span></label>
                    <select id="submittal_type" name="submittal_type" class="operator-select" required>
                        <option value="shop_drawing" @selected(old('submittal_type') === 'shop_drawing')>Shop drawing</option>
                        <option value="material_sample" @selected(old('submittal_type') === 'material_sample')>Mẫu vật liệu</option>
                        <option value="product_data" @selected(old('submittal_type') === 'product_data')>Tài liệu sản phẩm</option>
                        <option value="test_report" @selected(old('submittal_type') === 'test_report')>Báo cáo thí nghiệm</option>
                        <option value="other" @selected(old('submittal_type') === 'other')>Khác</option>
                    </select>
                    @error('submittal_type')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="specification_section">Mục spec</label>
                    <input id="specification_section" name="specification_section" type="text" class="operator-input" value="{{ old('specification_section') }}" placeholder="Ví dụ: 03 30 00">
                </div>

                <div class="operator-field">
                    <label for="due_date">Hạn duyệt</label>
                    <input id="due_date" name="due_date" type="date" class="operator-input" value="{{ old('due_date') }}">
                    @error('due_date')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="contractor">Nhà thầu</label>
                    <select id="contractor" name="contractor" class="operator-select">
                        <option value="">— Chọn nhà cung cấp —</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->name }}" @selected(old('contractor') === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                        @endforeach
                    </select>
                    @if (auth()->user()?->hasPermission('vendor.create'))
                        <a href="{{ route('operator.vendors.create') }}" target="_blank" class="text-sm text-teal-700 hover:underline">+ Thêm nhà cung cấp</a>
                    @endif
                    @error('contractor')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="manufacturer">Nhà sản xuất</label>
                    <select id="manufacturer" name="manufacturer" class="operator-select">
                        <option value="">— Chọn nhà cung cấp —</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->name }}" @selected(old('manufacturer') === $vendor->name)>{{ $vendor->name }}{{ $vendor->code ? ' (' . $vendor->code . ')' : '' }}</option>
                        @endforeach
                    </select>
                    @if (auth()->user()?->hasPermission('vendor.create'))
                        <a href="{{ route('operator.vendors.create') }}" target="_blank" class="text-sm text-teal-700 hover:underline">+ Thêm nhà cung cấp</a>
                    @endif
                    @error('manufacturer')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="operator-field">
                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="description">Mô tả <span class="text-rose-600">*</span></label>
                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description') }}</textarea>
                @error('description')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo submittal</button>
        </form>
    </x-ui.card>
@endsection

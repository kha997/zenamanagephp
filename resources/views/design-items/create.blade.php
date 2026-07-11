@extends('layouts.operator')

@section('title', 'Tạo công việc thiết kế')
@section('page_title', 'Tạo công việc thiết kế mới')

@section('content')
    <x-ui.page-header title="Tạo công việc thiết kế mới">
        <x-ui.button-link :href="route('operator.design-items.index')" variant="secondary">Quay lại bảng</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin công việc">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.design-items.store') }}" class="space-y-4">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">-- Chọn dự án --</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="item_type">Loại</label>
                    <select id="item_type" name="item_type" class="operator-select">
                        @foreach (['concept' => 'Ý tưởng', 'schematic' => 'Sơ bộ', 'technical' => 'Kỹ thuật', 'structural' => 'Kết cấu', 'mep' => 'MEP', 'interior' => 'Nội thất', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('item_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="operator-field">
                <label for="name">Tên công việc <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required placeholder="vd: Phối cảnh mặt tiền phương án 2">
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tạo</button>
        </form>
    </x-ui.card>
@endsection

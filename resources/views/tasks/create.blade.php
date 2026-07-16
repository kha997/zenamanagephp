@extends('layouts.operator')

@section('title', 'Tạo công việc')
@section('page_title', 'Tạo công việc')

@section('content')
    <x-ui.page-header
        title="Tạo công việc"
        description="Thêm công việc mới vào dự án."
    >
        <x-ui.button-link :href="route('app.tasks')" variant="secondary">Quay lại</x-ui.button-link>
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

        <form method="POST" action="{{ route('app.tasks.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $projectId) === (string) $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="name">Tên công việc <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required>
                </div>

                <div class="operator-field">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status" class="operator-select">
                        @foreach (['pending' => 'Chờ xử lý', 'in_progress' => 'Đang làm', 'completed' => 'Hoàn thành', 'on_hold' => 'Tạm dừng', 'cancelled' => 'Hủy'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="priority">Ưu tiên</label>
                    <select id="priority" name="priority" class="operator-select">
                        @foreach (['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao', 'urgent' => 'Khẩn'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('priority', 'medium') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="start_date">Bắt đầu</label>
                    <input id="start_date" name="start_date" type="date" class="operator-input" value="{{ old('start_date') }}">
                </div>

                <div class="operator-field">
                    <label for="end_date">Kết thúc</label>
                    <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date') }}">
                </div>
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea">{{ old('description') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo công việc</button>
        </form>
    </x-ui.card>
@endsection

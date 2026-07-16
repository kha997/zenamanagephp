@extends('layouts.operator')

@section('title', 'Sửa công việc')
@section('page_title', 'Sửa công việc')

@section('content')
    @if (!$task)
        <x-ui.empty-state
            title="Không tìm thấy công việc"
            description="{{ $error ?? 'Công việc không tồn tại hoặc bạn không có quyền truy cập.' }}"
        >
            <x-ui.button-link :href="route('app.tasks')" variant="secondary">Về danh sách</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.page-header
            title="Sửa công việc: {{ $task->name ?? $task->title }}"
            description="Cập nhật thông tin công việc."
        >
            <x-ui.button-link href="/app/tasks/{{ $task->id }}" variant="secondary">Quay lại</x-ui.button-link>
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

            <form method="POST" action="{{ route('app.tasks.update', $task->id) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="name">Tên công việc <span class="text-rose-600">*</span></label>
                        <input id="name" name="name" type="text" class="operator-input" value="{{ old('name', $task->name ?? $task->title) }}" required>
                    </div>

                    <div class="operator-field">
                        <label for="status">Trạng thái</label>
                        <select id="status" name="status" class="operator-select">
                            @foreach (['pending' => 'Chờ xử lý', 'in_progress' => 'Đang làm', 'completed' => 'Hoàn thành', 'on_hold' => 'Tạm dừng', 'cancelled' => 'Hủy'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $task->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="operator-field">
                        <label for="priority">Ưu tiên</label>
                        <select id="priority" name="priority" class="operator-select">
                            @foreach (['low' => 'Thấp', 'medium' => 'Trung bình', 'high' => 'Cao', 'urgent' => 'Khẩn'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', $task->priority) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="operator-field">
                        <label for="progress_percent">Tiến độ (%)</label>
                        <input id="progress_percent" name="progress_percent" type="number" min="0" max="100" class="operator-input" value="{{ old('progress_percent', (int) $task->progress_percent) }}">
                    </div>

                    <div class="operator-field">
                        <label for="start_date">Bắt đầu</label>
                        <input id="start_date" name="start_date" type="date" class="operator-input" value="{{ old('start_date', substr((string) $task->start_date, 0, 10)) }}">
                    </div>

                    <div class="operator-field">
                        <label for="end_date">Kết thúc</label>
                        <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date', substr((string) $task->end_date, 0, 10)) }}">
                    </div>
                </div>

                <div class="operator-field">
                    <label for="description">Mô tả</label>
                    <textarea id="description" name="description" class="operator-textarea">{{ old('description', $task->description) }}</textarea>
                </div>

                <button type="submit" class="operator-button operator-button-primary">Lưu thay đổi</button>
            </form>
        </x-ui.card>
    @endif
@endsection

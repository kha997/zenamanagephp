@extends('layouts.operator')

@section('title', 'Sửa dự án')
@section('page_title', 'Sửa dự án')

@section('content')
    <x-ui.page-header
        title="Sửa dự án: {{ $projectData->name }}"
        description="Cập nhật thông tin dự án."
    >
        <x-ui.button-link href="/app/projects/{{ $projectData->id }}" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin dự án">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('app.projects.update', $projectData->id) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="name">Tên dự án <span class="text-rose-600">*</span></label>
                    <input id="name" name="name" type="text" class="operator-input" value="{{ old('name', $projectData->name) }}" required>
                </div>

                <div class="operator-field">
                    <label for="status">Trạng thái</label>
                    <select id="status" name="status" class="operator-select">
                        @foreach (['planning' => 'Lập kế hoạch', 'active' => 'Đang chạy', 'on_hold' => 'Tạm dừng', 'completed' => 'Hoàn thành'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $projectData->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="start_date">Bắt đầu <span class="text-rose-600">*</span></label>
                    <input id="start_date" name="start_date" type="date" class="operator-input" value="{{ old('start_date', substr((string) $projectData->start_date, 0, 10)) }}" required>
                </div>

                <div class="operator-field">
                    <label for="end_date">Kết thúc <span class="text-rose-600">*</span></label>
                    <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date', substr((string) $projectData->end_date, 0, 10)) }}" required>
                </div>

                <div class="operator-field">
                    <label for="budget_planned">Ngân sách kế hoạch</label>
                    <input id="budget_planned" name="budget_planned" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('budget_planned', $projectData->budget_planned) }}">
                </div>

                <div class="operator-field">
                    <label for="pm_id">Quản lý dự án</label>
                    <select id="pm_id" name="pm_id" class="operator-select">
                        <option value="">Không chọn</option>
                        @foreach ($users as $member)
                            <option value="{{ $member->id }}" @selected(old('pm_id', (string) $projectData->pm_id) === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="client_id">Khách hàng</label>
                    <select id="client_id" name="client_id" class="operator-select">
                        <option value="">Không chọn</option>
                        @foreach ($users as $member)
                            <option value="{{ $member->id }}" @selected(old('client_id', (string) $projectData->client_id) === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="operator-field">
                <label for="description">Mô tả</label>
                <textarea id="description" name="description" class="operator-textarea">{{ old('description', $projectData->description) }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Lưu thay đổi</button>
        </form>
    </x-ui.card>
@endsection

@extends('layouts.operator')

@section('title', 'Tạo RFI')
@section('page_title', 'Tạo RFI')

@section('content')
    <x-ui.page-header
        title="Tạo RFI"
        description="Gửi yêu cầu thông tin mới cho dự án."
    >
        <x-ui.button-link :href="route('operator.rfis.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin RFI">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.rfis.store') }}" class="space-y-5">
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
                    <label for="priority">Mức ưu tiên <span class="text-rose-600">*</span></label>
                    <select id="priority" name="priority" class="operator-select" required>
                        <option value="low" @selected(old('priority') === 'low')>Thấp</option>
                        <option value="medium" @selected(old('priority', 'medium') === 'medium')>Trung bình</option>
                        <option value="high" @selected(old('priority') === 'high')>Cao</option>
                        <option value="urgent" @selected(old('priority') === 'urgent')>Khẩn cấp</option>
                    </select>
                    @error('priority')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="due_date">Hạn trả lời</label>
                    <input id="due_date" name="due_date" type="date" class="operator-input" value="{{ old('due_date') }}">
                    @error('due_date')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="assigned_to">Giao cho</label>
                    <select id="assigned_to" name="assigned_to" class="operator-select">
                        <option value="">Chưa giao</option>
                        @foreach ($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected(old('assigned_to') === (string) $assignee->id)>
                                {{ $assignee->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('assigned_to')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="location">Vị trí</label>
                    <input id="location" name="location" type="text" class="operator-input" value="{{ old('location') }}" placeholder="Ví dụ: Tầng 3, trục A-B">
                </div>

                <div class="operator-field">
                    <label for="drawing_reference">Bản vẽ tham chiếu</label>
                    <input id="drawing_reference" name="drawing_reference" type="text" class="operator-input" value="{{ old('drawing_reference') }}" placeholder="Ví dụ: A-301 Rev.2">
                </div>
            </div>

            <div class="operator-field">
                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="description">Nội dung câu hỏi <span class="text-rose-600">*</span></label>
                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description') }}</textarea>
                @error('description')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo RFI</button>
        </form>
    </x-ui.card>
@endsection

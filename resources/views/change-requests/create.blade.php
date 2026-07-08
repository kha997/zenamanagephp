@extends('layouts.operator')

@section('title', 'Tạo yêu cầu thay đổi')
@section('page_title', 'Tạo yêu cầu thay đổi')

@section('content')
    <x-ui.page-header
        title="Tạo yêu cầu thay đổi"
        description="Đề xuất thay đổi phạm vi, chi phí hoặc tiến độ ở trạng thái nháp."
    >
        <x-ui.button-link :href="route('operator.change-requests.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin yêu cầu thay đổi">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.change-requests.store') }}" class="space-y-5">
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
                    <label for="change_type">Loại thay đổi <span class="text-rose-600">*</span></label>
                    <select id="change_type" name="change_type" class="operator-select" required>
                        <option value="scope" @selected(old('change_type') === 'scope')>Phạm vi</option>
                        <option value="cost" @selected(old('change_type') === 'cost')>Chi phí</option>
                        <option value="schedule" @selected(old('change_type') === 'schedule')>Tiến độ</option>
                        <option value="quality" @selected(old('change_type') === 'quality')>Chất lượng</option>
                        <option value="design" @selected(old('change_type') === 'design')>Thiết kế</option>
                        <option value="other" @selected(old('change_type') === 'other')>Khác</option>
                    </select>
                    @error('change_type')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
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
                    <label for="cost_impact">Tác động chi phí</label>
                    <input id="cost_impact" name="cost_impact" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('cost_impact') }}" placeholder="vd: 150.000.000">
                    @error('cost_impact')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>

                <div class="operator-field">
                    <label for="schedule_impact_days">Tác động tiến độ (ngày)</label>
                    <input id="schedule_impact_days" name="schedule_impact_days" type="number" min="0" class="operator-input" value="{{ old('schedule_impact_days') }}">
                    @error('schedule_impact_days')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="operator-field">
                <label for="title">Tiêu đề <span class="text-rose-600">*</span></label>
                <input id="title" name="title" type="text" class="operator-input" value="{{ old('title') }}" maxlength="255" required>
                @error('title')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="description">Mô tả thay đổi <span class="text-rose-600">*</span></label>
                <textarea id="description" name="description" class="operator-textarea" required>{{ old('description') }}</textarea>
                @error('description')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="impact_analysis">Phân tích tác động <span class="text-rose-600">*</span></label>
                <textarea id="impact_analysis" name="impact_analysis" class="operator-textarea" required>{{ old('impact_analysis') }}</textarea>
                @error('impact_analysis')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="justification">Lý do đề xuất <span class="text-rose-600">*</span></label>
                <textarea id="justification" name="justification" class="operator-textarea" required>{{ old('justification') }}</textarea>
                @error('justification')<span class="text-sm text-rose-600">{{ $message }}</span>@enderror
            </div>

            <div class="operator-field">
                <label for="alternatives_considered">Phương án thay thế đã cân nhắc</label>
                <textarea id="alternatives_considered" name="alternatives_considered" class="operator-textarea">{{ old('alternatives_considered') }}</textarea>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo yêu cầu</button>
        </form>
    </x-ui.card>
@endsection

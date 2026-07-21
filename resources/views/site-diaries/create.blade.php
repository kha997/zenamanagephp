@extends('layouts.operator')

@section('title', 'Tạo nhật ký công trường')
@section('page_title', 'Tạo nhật ký công trường')

@section('content')
    <x-ui.page-header
        title="Tạo nhật ký công trường"
        description="Ghi nhận thông tin công trường theo ngày. Mỗi dự án chỉ có một nhật ký cho mỗi ngày."
    >
        <x-ui.button-link :href="route('operator.site-diaries.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin nhật ký">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.site-diaries.store') }}" class="space-y-5">
            @csrf

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án</label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">Chọn dự án</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>
                                {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="operator-field">
                    <label for="diary_date">Ngày</label>
                    <input id="diary_date" name="diary_date" type="date" class="operator-input" value="{{ old('diary_date', now()->format('Y-m-d')) }}" required>
                </div>

                <div class="operator-field">
                    <label for="manpower_count">Số nhân lực</label>
                    <input id="manpower_count" name="manpower_count" type="number" min="0" class="operator-input" value="{{ old('manpower_count', 0) }}">
                </div>

                <div class="operator-field">
                    <label for="weather">Thời tiết</label>
                    <input id="weather" name="weather" type="text" class="operator-input" value="{{ old('weather') }}" placeholder="Nắng, mưa, âm u...">
                </div>

                <div class="operator-field">
                    <label for="temperature">Nhiệt độ</label>
                    <input id="temperature" name="temperature" type="text" class="operator-input" value="{{ old('temperature') }}" placeholder="28-34°C">
                </div>
            </div>

            <div class="operator-field">
                <label for="work_performed">Công việc thực hiện</label>
                <textarea id="work_performed" name="work_performed" class="operator-textarea" required>{{ old('work_performed') }}</textarea>
            </div>

            <div class="operator-field">
                <label for="equipment_used">Thiết bị sử dụng</label>
                <textarea id="equipment_used" name="equipment_used" class="operator-textarea">{{ old('equipment_used') }}</textarea>
            </div>

            <div class="operator-field">
                <label for="materials_delivered">Vật tư nhập về</label>
                <textarea id="materials_delivered" name="materials_delivered" class="operator-textarea">{{ old('materials_delivered') }}</textarea>
            </div>

            <div class="operator-field">
                <label for="safety_notes">Ghi chú an toàn</label>
                <textarea id="safety_notes" name="safety_notes" class="operator-textarea">{{ old('safety_notes') }}</textarea>
            </div>

            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="visitors">Khách/đoàn kiểm tra</label>
                    <textarea id="visitors" name="visitors" class="operator-textarea">{{ old('visitors') }}</textarea>
                </div>

                <div class="operator-field">
                    <label for="delays_issues">Chậm trễ / sự cố</label>
                    <textarea id="delays_issues" name="delays_issues" class="operator-textarea">{{ old('delays_issues') }}</textarea>
                </div>
            </div>

            <button type="submit" class="operator-button operator-button-primary">Tạo nhật ký</button>
        </form>

        <script>
            (function () {
                var autofillByProject = @js($autofillByProject);
                var fieldIds = ['weather', 'temperature', 'manpower_count', 'equipment_used'];
                var touched = {};

                fieldIds.forEach(function (fieldId) {
                    touched[fieldId] = false;
                    var el = document.getElementById(fieldId);
                    if (!el) {
                        return;
                    }
                    el.addEventListener('input', function () {
                        touched[fieldId] = true;
                    });
                });

                var projectSelect = document.getElementById('project_id');
                if (!projectSelect) {
                    return;
                }

                projectSelect.addEventListener('change', function () {
                    var data = autofillByProject[projectSelect.value];
                    if (!data) {
                        return;
                    }

                    fieldIds.forEach(function (fieldId) {
                        if (touched[fieldId]) {
                            return;
                        }
                        if (!(fieldId in data) || data[fieldId] === null) {
                            return;
                        }

                        var el = document.getElementById(fieldId);
                        if (el) {
                            el.value = data[fieldId];
                        }
                    });
                });
            })();
        </script>
    </x-ui.card>
@endsection

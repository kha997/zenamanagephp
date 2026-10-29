@extends('layouts.operator')

@php
    $templateName = $instance->templateVersion?->template?->name ?? 'Công việc theo mẫu';
    $statusLabels = [
        'pending' => 'Chờ xử lý',
        'in_progress' => 'Đang làm',
        'completed' => 'Hoàn thành',
        'blocked' => 'Bị chặn',
        'approved' => 'Đã duyệt',
        'rejected' => 'Bị từ chối',
    ];
@endphp

@section('title', $templateName)
@section('page_title', $templateName)

@section('content')
    <x-ui.page-header
        :title="$templateName"
        :description="'Dự án: ' . $project->name . ' — phiên bản ' . ($instance->templateVersion?->semver ?? '—')"
    >
        <x-ui.button-link :href="route('app.projects.work-instances.index', $project->id)" variant="secondary">Danh sách công việc theo mẫu</x-ui.button-link>
    </x-ui.page-header>

    @if (session('error'))
        <div class="operator-error-list">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="operator-error-list">
            <ul class="space-y-1 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        @foreach ($instance->steps as $step)
            @php
                $valuesByKey = $step->values->keyBy('field_key');
                $fields = collect($step->snapshot_fields_json ?? [])
                    ->filter(static fn ($field) => is_array($field) && isset($field['field_key']))
                    ->values();
                // Only these types round-trip through a plain input; json / datetime values stay read-only so a save
                // cannot overwrite them.
                $editableFields = $fields->filter(static fn ($field) => ! in_array($field['type'] ?? 'string', ['json', 'object', 'array', 'datetime'], true));
                $stepRouteParams = ['project' => $project->id, 'instance' => $instance->id, 'step' => $step->id];
            @endphp

            <x-ui.card :title="$step->step_order . '. ' . $step->name">
                <div class="space-y-4">
                    <x-ui.field-value label="Trạng thái" :value="$statusLabels[$step->status] ?? $step->status" />
                    <x-ui.field-value label="Người thực hiện" :value="$assignees->firstWhere('id', $step->assignee_id)?->name ?? '—'" />
                    <x-ui.field-value label="Hạn" :value="optional($step->deadline_at)->format('d/m/Y H:i') ?? '—'" />

                    @foreach ($fields as $field)
                        @php
                            $value = $valuesByKey->get($field['field_key']);
                            $display = $value?->value_string
                                ?? ($value?->value_number !== null ? (string) $value->value_number : null)
                                ?? optional($value?->value_date)->format('d/m/Y')
                                ?? optional($value?->value_datetime)->format('d/m/Y H:i')
                                ?? ($value?->value_json !== null ? json_encode($value->value_json, JSON_UNESCAPED_UNICODE) : null);
                        @endphp
                        <x-ui.field-value :label="$field['label'] ?? $field['field_key']" :value="$display ?? '—'" />
                    @endforeach


                    <form method="POST" action="{{ route('app.projects.work-instances.steps.update', $stepRouteParams) }}" class="space-y-3">
                        @csrf
                        <div class="operator-field">
                            <label for="status-{{ $step->id }}">Cập nhật trạng thái</label>
                            <select id="status-{{ $step->id }}" name="status" class="operator-select">
                                <option value="" selected>— Giữ nguyên ({{ $statusLabels[$step->status] ?? $step->status }}) —</option>
                                @foreach ($editableStatuses as $status)
                                    <option value="{{ $status }}">{{ $statusLabels[$status] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="assignee-{{ $step->id }}">Người thực hiện</label>
                            <select id="assignee-{{ $step->id }}" name="assignee_id" class="operator-select">
                                <option value="">— Giữ nguyên —</option>
                                @foreach ($assignees as $assignee)
                                    <option value="{{ $assignee->id }}" @selected((string) $step->assignee_id === (string) $assignee->id)>{{ $assignee->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="deadline-{{ $step->id }}">Hạn</label>
                            <input id="deadline-{{ $step->id }}" name="deadline_at" type="datetime-local" class="operator-input" value="{{ optional($step->deadline_at)->format('Y-m-d\TH:i') }}">
                        </div>
                        @foreach ($editableFields as $field)
                            @php
                                $value = $valuesByKey->get($field['field_key']);
                                $fieldType = $field['type'] ?? 'string';
                                $inputId = 'field-' . $step->id . '-' . $field['field_key'];
                                $inputName = 'field_values[' . $field['field_key'] . ']';
                            @endphp
                            <div class="operator-field">
                                <label for="{{ $inputId }}">{{ $field['label'] ?? $field['field_key'] }}</label>
                                @if ($fieldType === 'number')
                                    <input id="{{ $inputId }}" name="{{ $inputName }}" type="number" step="any" class="operator-input" value="{{ $value?->value_number }}">
                                @elseif ($fieldType === 'date')
                                    <input id="{{ $inputId }}" name="{{ $inputName }}" type="date" class="operator-input" value="{{ optional($value?->value_date)->format('Y-m-d') }}">
                                @else
                                    {{-- A textarea keeps multiline text intact; a text input would drop its line breaks. --}}
                                    <textarea id="{{ $inputId }}" name="{{ $inputName }}" rows="2" class="operator-input">{{ $value?->value_string }}</textarea>
                                @endif
                            </div>
                        @endforeach
                        <button type="submit" class="operator-button operator-button-primary">Lưu bước</button>
                    </form>

                    <form method="POST" action="{{ route('app.projects.work-instances.steps.approve', $stepRouteParams) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="operator-field" style="max-width:320px">
                            <label for="comment-{{ $step->id }}">Ghi chú duyệt</label>
                            <input id="comment-{{ $step->id }}" name="comment" type="text" class="operator-input">
                        </div>
                        <button type="submit" name="decision" value="approved" class="operator-button operator-button-secondary">Duyệt bước</button>
                        <button type="submit" name="decision" value="rejected" class="operator-button operator-button-inline">Từ chối</button>
                    </form>

                    <div class="space-y-2">
                        <p class="text-sm font-semibold text-slate-700">Tệp đính kèm</p>
                        @forelse ($step->attachments as $attachment)
                            <form method="POST" action="{{ route('app.projects.work-instances.steps.attachments.destroy', $stepRouteParams + ['attachment' => $attachment->id]) }}" class="flex items-center gap-3 text-sm text-slate-700">
                                @csrf
                                <span>{{ $attachment->file_name }}</span>
                                <button type="submit" class="operator-button operator-button-inline">Xoá</button>
                            </form>
                        @empty
                            <p class="text-sm text-slate-600">Chưa có tệp.</p>
                        @endforelse
                        <form method="POST" action="{{ route('app.projects.work-instances.steps.attachments.store', $stepRouteParams) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <input name="file" type="file" class="operator-input" style="max-width:320px" required>
                            <button type="submit" class="operator-button operator-button-secondary">Tải lên</button>
                        </form>
                    </div>
                </div>
            </x-ui.card>
        @endforeach

        <x-ui.card title="Xuất báo cáo">
            @if ($deliverableVersions->isEmpty())
                <p class="text-sm text-slate-600">Chưa có mẫu báo cáo đã phát hành.</p>
            @else
                <form method="POST" action="{{ route('app.projects.work-instances.export', ['project' => $project->id, 'instance' => $instance->id]) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="operator-field" style="max-width:320px">
                        <label for="deliverable_template_version_id">Mẫu báo cáo</label>
                        <select id="deliverable_template_version_id" name="deliverable_template_version_id" class="operator-select">
                            @foreach ($deliverableVersions as $version)
                                <option value="{{ $version->id }}">{{ $version->template?->name ?? '—' }} ({{ $version->semver }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field" style="max-width:160px">
                        <label for="format">Định dạng</label>
                        <select id="format" name="format" class="operator-select">
                            <option value="html">HTML</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Xuất</button>
                </form>
            @endif
        </x-ui.card>
    </div>
@endsection

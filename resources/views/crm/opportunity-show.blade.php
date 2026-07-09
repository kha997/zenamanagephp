@extends('layouts.operator')

@section('title', $opportunity->opportunity_name . ' — Cơ hội')
@section('page_title', 'Chi tiết cơ hội')

@php
    $stageLabels = [
        'new_lead' => 'Lead mới', 'qualified' => 'Đã sàng lọc', 'contacted' => 'Đã liên hệ',
        'brief_discovery' => 'Khai thác nhu cầu', 'survey_or_inputs_received' => 'Đã khảo sát/nhận đầu vào',
        'scope_defined' => 'Chốt phạm vi', 'proposal_draft' => 'Nháp báo giá', 'proposal_sent' => 'Đã gửi báo giá',
        'negotiation' => 'Đàm phán', 'contracting' => 'Soạn hợp đồng',
        'won' => 'Thắng', 'lost' => 'Thua', 'nurture' => 'Nuôi dưỡng', 'no_bid' => 'Không tham gia',
    ];
@endphp

@section('content')
    <x-ui.page-header
        title="{{ $opportunity->opportunity_name }}"
        description="Khách hàng: {{ $opportunity->account?->display_name ?? '—' }}"
    >
        <x-ui.button-link :href="route('operator.crm.index')" variant="secondary">Pipeline</x-ui.button-link>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.card>
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Thông tin cơ hội">
        <div class="operator-form-grid">
            <x-ui.field-value label="Giai đoạn">
                <x-ui.status-badge :status="$opportunity->pipeline_stage" />
                <span class="ml-1 text-sm text-slate-600">{{ $stageLabels[$opportunity->pipeline_stage] ?? '' }}</span>
            </x-ui.field-value>
            <x-ui.field-value label="Dịch vụ" :value="$opportunity->service_category" />
            <x-ui.field-value label="Phí dự kiến" :value="$opportunity->estimated_fee ? number_format((float) $opportunity->estimated_fee, 0, ',', '.') . '₫' : '—'" />
            <x-ui.field-value label="Giá trị công trình" :value="$opportunity->estimated_project_value ? number_format((float) $opportunity->estimated_project_value, 0, ',', '.') . '₫' : '—'" />
            <x-ui.field-value label="Xác suất" :value="$opportunity->probability !== null ? $opportunity->probability . '%' : '—'" />
            <x-ui.field-value label="Dự kiến chốt" :value="optional($opportunity->expected_close_date)->format('d/m/Y') ?? '—'" />
            <x-ui.field-value label="Sales" :value="$opportunity->salesOwner?->name ?? '—'" />
            <x-ui.field-value label="Kỹ thuật" :value="$opportunity->technicalOwner?->name ?? '—'" />
            <x-ui.field-value label="Liên hệ KH" :value="$opportunity->account?->phone ?? $opportunity->account?->email ?? '—'" />
            @if ($opportunity->lost_reason)
                <x-ui.field-value label="Lý do thua" :value="$opportunity->lost_reason" />
            @endif
            @if ($opportunity->convertedProject)
                <x-ui.field-value label="Dự án">
                    <a href="/app/projects/{{ $opportunity->convertedProject->id }}" class="operator-link">
                        {{ $opportunity->convertedProject->name }} ({{ $opportunity->convertedProject->code }})
                    </a>
                </x-ui.field-value>
            @endif
        </div>

        @if ($opportunity->service_scope_summary)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $opportunity->service_scope_summary }}</p>
        @endif
    </x-ui.card>

    @if (!$opportunity->isTerminal())
        <x-ui.card title="Chuyển giai đoạn">
            <form method="POST" action="{{ route('operator.crm.opportunities.stage', $opportunity->id) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="operator-field flex-1 min-w-64">
                    <label for="pipeline_stage">Giai đoạn mới</label>
                    <select id="pipeline_stage" name="pipeline_stage" class="operator-select">
                        @foreach ($stageLabels as $value => $label)
                            <option value="{{ $value }}" @selected($opportunity->pipeline_stage === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field flex-1 min-w-64">
                    <label for="lost_reason">Lý do (bắt buộc nếu Thua)</label>
                    <input id="lost_reason" name="lost_reason" type="text" class="operator-input" value="{{ old('lost_reason') }}">
                </div>
                <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
            </form>
        </x-ui.card>
    @endif

    @if ((string) $opportunity->pipeline_stage === 'won' && !$opportunity->converted_project_id)
        <x-ui.card title="Tạo dự án từ cơ hội thắng">
            <form method="POST" action="{{ route('operator.crm.opportunities.convert', $opportunity->id) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="operator-field flex-1 min-w-64">
                    <label for="project_name">Tên dự án</label>
                    <input id="project_name" name="project_name" type="text" class="operator-input" value="{{ old('project_name', $opportunity->opportunity_name) }}">
                </div>
                <div class="operator-field">
                    <label for="start_date">Bắt đầu</label>
                    <input id="start_date" name="start_date" type="date" class="operator-input" value="{{ old('start_date') }}">
                </div>
                <div class="operator-field">
                    <label for="end_date">Kết thúc</label>
                    <input id="end_date" name="end_date" type="date" class="operator-input" value="{{ old('end_date') }}">
                </div>
                <button type="submit" class="operator-button operator-button-primary">Tạo dự án</button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Lịch sử">
        @if ($events->isEmpty())
            <p class="text-sm text-slate-500">Chưa có sự kiện.</p>
        @else
            <ul class="space-y-2">
                @foreach ($events as $event)
                    <li class="text-sm">
                        <span class="font-medium text-slate-900">{{ $event->event_key }}</span>
                        <span class="text-slate-500">— {{ $event->actor?->name ?? 'Hệ thống' }} · {{ optional($event->occurred_at)->format('d/m/Y H:i') }}</span>
                        @if (($event->payload['from'] ?? null) && ($event->payload['to'] ?? null))
                            <span class="text-slate-500">({{ $stageLabels[$event->payload['from']] ?? $event->payload['from'] }} → {{ $stageLabels[$event->payload['to']] ?? $event->payload['to'] }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection

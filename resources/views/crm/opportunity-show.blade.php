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
    $appointmentTypeLabels = [
        'consultation' => 'Tư vấn',
        'survey' => 'Khảo sát',
        'site_visit' => 'Tham quan',
        'meeting' => 'Họp',
    ];
    $appointmentStatusMeta = [
        'scheduled' => ['label' => 'Đã đặt lịch', 'classes' => 'bg-amber-100 text-amber-800'],
        'completed' => ['label' => 'Hoàn thành', 'classes' => 'bg-emerald-100 text-emerald-800'],
        'cancelled' => ['label' => 'Đã hủy', 'classes' => 'bg-rose-100 text-rose-800'],
        'rescheduled' => ['label' => 'Đã dời lịch', 'classes' => 'bg-slate-200 text-slate-600'],
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

    <x-ui.card title="Lịch hẹn">
        @if ($appointments->isEmpty())
            <p class="text-sm text-slate-500">Chưa có lịch hẹn.</p>
        @else
            <div class="overflow-x-auto">
                <table class="operator-table text-sm">
                    <thead>
                        <tr>
                            <th>Loại</th>
                            <th>Ngày giờ</th>
                            <th>Người phụ trách</th>
                            <th>Trạng thái</th>
                            <th>Kết quả</th>
                            @if (auth()->user()?->hasPermission('crm.manage'))
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($appointments as $appointment)
                            @php
                                $statusMeta = $appointmentStatusMeta[$appointment->status] ?? ['label' => $appointment->status, 'classes' => 'bg-slate-100 text-slate-700'];
                            @endphp
                            <tr>
                                <td class="font-medium">{{ $appointmentTypeLabels[$appointment->type] ?? $appointment->type }}</td>
                                <td>
                                    <div>{{ optional($appointment->scheduled_at)->format('d/m/Y H:i') ?? '—' }}</div>
                                    @if ($appointment->location)
                                        <div class="text-xs text-slate-500">{{ $appointment->location }}</div>
                                    @endif
                                </td>
                                <td>{{ $appointment->assignee?->name ?? '—' }}</td>
                                <td>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusMeta['classes'] }}">
                                        {{ $statusMeta['label'] }}
                                    </span>
                                </td>
                                <td class="whitespace-pre-line text-sm text-slate-600">{{ $appointment->outcome_notes ?: '—' }}</td>
                                @if (auth()->user()?->hasPermission('crm.manage'))
                                    <td class="align-top">
                                        @if ($appointment->status === \App\Models\OpportunityAppointment::STATUS_SCHEDULED)
                                            <div class="flex min-w-72 flex-col gap-3">
                                                <form method="POST" action="{{ route('operator.crm.appointments.complete', $appointment->id) }}" class="space-y-2">
                                                    @csrf
                                                    <div class="operator-field">
                                                        <label for="complete_outcome_notes_{{ $appointment->id }}">Kết quả hoàn thành</label>
                                                        <textarea id="complete_outcome_notes_{{ $appointment->id }}" name="outcome_notes" class="operator-input" rows="2" required>{{ old('outcome_notes') }}</textarea>
                                                    </div>
                                                    <button type="submit" class="operator-button operator-button-primary">Hoàn thành</button>
                                                </form>

                                                <form method="POST" action="{{ route('operator.crm.appointments.cancel', $appointment->id) }}" class="space-y-2" onsubmit="return confirm('Hủy lịch hẹn này?')">
                                                    @csrf
                                                    <div class="operator-field">
                                                        <label for="cancel_outcome_notes_{{ $appointment->id }}">Lý do hủy</label>
                                                        <textarea id="cancel_outcome_notes_{{ $appointment->id }}" name="outcome_notes" class="operator-input" rows="2">{{ old('outcome_notes') }}</textarea>
                                                    </div>
                                                    <button type="submit" class="operator-button">Hủy</button>
                                                </form>

                                                <form method="POST" action="{{ route('operator.crm.appointments.reschedule', $appointment->id) }}" class="space-y-2">
                                                    @csrf
                                                    <div class="operator-field">
                                                        <label for="reschedule_scheduled_at_{{ $appointment->id }}">Ngày giờ mới</label>
                                                        <input
                                                            id="reschedule_scheduled_at_{{ $appointment->id }}"
                                                            name="scheduled_at"
                                                            type="datetime-local"
                                                            class="operator-input"
                                                            value="{{ old('scheduled_at', optional($appointment->scheduled_at)->format('Y-m-d\\TH:i')) }}"
                                                            required
                                                        >
                                                    </div>
                                                    <button type="submit" class="operator-button">Dời lịch</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (auth()->user()?->hasPermission('crm.manage'))
            <form method="POST" action="{{ route('operator.crm.opportunities.appointments.store', $opportunity->id) }}" class="mt-4 space-y-3">
                @csrf
                <p class="text-sm font-semibold text-slate-900">Đặt lịch mới</p>
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="appointment_type">Loại</label>
                        <select id="appointment_type" name="type" class="operator-select" required>
                            <option value="">Chọn loại</option>
                            @foreach ($appointmentTypeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="appointment_scheduled_at">Ngày giờ</label>
                        <input id="appointment_scheduled_at" name="scheduled_at" type="datetime-local" class="operator-input" value="{{ old('scheduled_at') }}" required>
                    </div>
                    <div class="operator-field">
                        <label for="appointment_location">Địa điểm</label>
                        <input id="appointment_location" name="location" type="text" class="operator-input" value="{{ old('location') }}">
                    </div>
                    <div class="operator-field">
                        <label for="appointment_assigned_to">Người phụ trách</label>
                        <select id="appointment_assigned_to" name="assigned_to" class="operator-select">
                            <option value="">Chưa phân công</option>
                            @foreach ($users as $userOption)
                                <option value="{{ $userOption->id }}" @selected(old('assigned_to') === (string) $userOption->id)>{{ $userOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button type="submit" class="operator-button operator-button-primary">Đặt lịch</button>
            </form>
        @endif
    </x-ui.card>

    @if ($boqIntegrationEnabled)
        <x-ui.card title="Báo giá — zena-boq-core">
            @if ($boqCard === null)
                <p class="text-sm text-slate-500">Chưa liên kết báo giá.</p>
                @if ($canManageBoq)
                    <form method="POST" action="{{ route('operator.crm.opportunities.boq-link', $opportunity->id) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="operator-field flex-1 min-w-64">
                            <label for="external_boq_project_code">Mã dự án zena-boq-core</label>
                            <input id="external_boq_project_code" name="external_boq_project_code" type="text" class="operator-input" value="{{ old('external_boq_project_code') }}" required placeholder="vd: PRJ-001">
                        </div>
                        <button type="submit" class="operator-button operator-button-primary">Liên kết</button>
                    </form>
                @endif
            @else
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã dự án" :value="$boqCard['project_code']" />
                    <x-ui.field-value label="Trạng thái">
                        @if ($boqCard['status'])
                            <x-ui.status-badge :status="$boqCard['status']" />
                        @else
                            —
                        @endif
                    </x-ui.field-value>
                    <x-ui.field-value label="Hiệu chỉnh giá">
                        @if ($boqCard['calibration'])
                            <x-ui.calibration-badge :status="$boqCard['calibration']" />
                        @else
                            —
                        @endif
                    </x-ui.field-value>
                    <x-ui.field-value label="Tổng tiền" :value="$boqCard['total'] !== null ? number_format($boqCard['total'], 0, ',', '.') . '₫' : '—'" />
                    <x-ui.field-value label="Đồng bộ lần cuối">
                        @if ($boqCard['synced_at'])
                            <span class="{{ $boqCard['is_stale'] ? 'text-amber-600 font-semibold' : '' }}">{{ $boqCard['synced_at']->diffForHumans() }}</span>
                        @else
                            Chưa đồng bộ
                        @endif
                    </x-ui.field-value>
                    @if ($boqCard['external_url'])
                        <x-ui.field-value label="Xem trên zena-boq-core">
                            <a href="{{ $boqCard['external_url'] }}" target="_blank" rel="noopener" class="operator-link">Mở báo giá ↗</a>
                        </x-ui.field-value>
                    @endif
                </div>

                @if ($canManageBoq)
                    <form method="POST" action="{{ route('operator.crm.opportunities.boq-sync', $opportunity->id) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Đồng bộ báo giá</button>
                    </form>
                @endif
            @endif
        </x-ui.card>
    @endif

    {{-- Native Quotes --}}
    <x-ui.card title="Báo giá (native)">
        @php
            $nativeQuotes = \App\Models\Quote::query()
                ->where('tenant_id', (string) $opportunity->tenant_id)
                ->where('opportunity_id', $opportunity->id)
                ->withCount('lines')
                ->orderByDesc('revision_no')
                ->get();
        @endphp

        @if ($nativeQuotes->isEmpty())
            <p class="text-sm text-slate-500">Chưa có báo giá.</p>
        @else
            <div class="overflow-x-auto">
                <table class="operator-table text-sm">
                    <thead>
                        <tr>
                            <th>Số</th>
                            <th>Rev</th>
                            <th>Tổng</th>
                            <th>Trạng thái</th>
                            <th>Ngày gửi</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nativeQuotes as $q)
                            <tr>
                                <td class="font-medium">{{ $q->quote_number }}</td>
                                <td>{{ $q->revision_no }}</td>
                                <td>{{ number_format($q->total, 0, ',', '.') }}₫</td>
                                <td><x-ui.status-badge :status="$q->status" /></td>
                                <td>{{ $q->sent_at ? $q->sent_at->format('d/m/Y') : '—' }}</td>
                                <td>
                                    <a href="{{ route('operator.crm.quotes.show', $q->id) }}" class="operator-link">Xem</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (auth()->user()?->hasPermission('crm.manage'))
            <form method="POST" action="{{ route('operator.crm.opportunities.quotes.store', $opportunity->id) }}" class="mt-3">
                @csrf
                <button type="submit" class="operator-button operator-button-primary">Tạo báo giá</button>
            </form>
        @endif
    </x-ui.card>

    @if (auth()->user()?->hasPermission('ai.suggest'))
        <x-ui.card title="Tóm tắt AI">
            <div data-ai-summary data-opportunity-id="{{ $opportunity->id }}">
                <p class="text-sm text-slate-500">AI tóm tắt cơ hội này từ lead gốc, lịch hẹn và báo giá — dùng để chuẩn bị trước cuộc gặp khách.</p>
                <button type="button" class="operator-button operator-button-secondary mt-2" data-ai-summary-trigger>Tạo tóm tắt</button>
                <span class="text-xs text-slate-500" data-ai-summary-status></span>
                <div class="mt-3 hidden whitespace-pre-line text-sm text-slate-700" data-ai-summary-result></div>
                <p class="mt-2 hidden text-xs text-slate-400" data-ai-summary-caption></p>
            </div>
        </x-ui.card>
    @endif

    @if ($contractCard !== null)
        <x-ui.card title="Hợp đồng">
            @if ($contractCard['has_drift'])
                <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
                    Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp.
                </p>
            @endif

            @if ($contractCard['contract'])
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã hợp đồng" :value="$contractCard['contract']['code']" />
                </div>
                <a href="{{ route('operator.contracts.show', $contractCard['contract']['id']) }}" class="operator-link mt-3 inline-block">Xem hợp đồng</a>
            @elseif ($contractCard['eligible'] && $canManageBoq)
                <form method="POST" action="{{ route('operator.crm.opportunities.create-contract', $opportunity->id) }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Tạo hợp đồng</button>
                </form>
            @endif
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

@extends('layouts.operator')

@section('title', $project->name . ' — OPPM một trang')
@section('page_title', 'OPPM một trang')

@php
    use App\Services\ProjectHealth\ProjectHealthReadService as Health;

    /** @var array<string, mixed> $oppm */
    $health = $oppm['health'];
    $progress = $health['progress'];
    $milestones = $health['schedule']['milestones'];
    $attention = $health['attention'];
    $finance = $health['finance'];
    $money = static fn (array $measure): string => $measure['availability'] === Health::AVAILABLE
        ? number_format((float) $measure['value'], 0, ',', '.')
        : 'Chưa có dữ liệu';
    $basisLabels = [
        Health::BASIS_COMPONENT_COST_WEIGHTED => 'theo chi phí hạng mục',
        Health::BASIS_TASK_AVERAGE => 'theo công việc, độ tin cậy hạn chế',
    ];
    $statusLabels = [
        'pending' => 'Chưa bắt đầu',
        'in_progress' => 'Đang làm',
        'on_hold' => 'Tạm dừng',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã huỷ',
    ];
@endphp

@section('content')
    <style>
        .oppm-bar-track { position: relative; height: 0.5rem; border-radius: 9999px; background: #e2e8f0; }
        .oppm-bar { position: absolute; top: 0; height: 0.5rem; border-radius: 9999px; background: #0f766e; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { font-size: 10px; }
            nav, aside, header, footer, .oppm-no-print { display: none !important; }
            .operator-panel { break-inside: avoid; box-shadow: none; }
            .oppm-bar { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    <x-ui.page-header
        title="{{ $project->name }}"
        description="OPPM một trang — {{ $project->code ?? '' }} · lập lúc {{ $oppm['generated_at']->format('d/m/Y H:i') }}"
    >
        <div class="oppm-no-print flex gap-2">
            <x-ui.button-link :href="route('app.projects.show', $project->id)" variant="secondary">Về trang dự án</x-ui.button-link>
            <button type="button" class="operator-button operator-button-secondary" onclick="window.print()">In</button>
        </div>
    </x-ui.page-header>

    <x-ui.card title="Thông tin dự án">
        <div class="operator-form-grid">
            <x-ui.field-value label="Mã dự án" :value="$project->code ?? '—'" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$project->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Khách hàng" :value="$project->client?->name ?? '—'" />
            <x-ui.field-value label="Quản lý dự án" :value="$project->manager?->name ?? '—'" />
            <x-ui.field-value label="Bắt đầu" :value="$project->start_date ? \Illuminate\Support\Carbon::parse($project->start_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value label="Kết thúc" :value="$project->end_date ? \Illuminate\Support\Carbon::parse($project->end_date)->format('d/m/Y') : '—'" />
            <x-ui.field-value
                label="Loại dịch vụ"
                :value="empty($oppm['service_lines']) ? 'Chưa phân loại' : implode(', ', array_column($oppm['service_lines'], 'label'))"
            />
        </div>
        @if ($project->description)
            <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $project->description }}</p>
        @endif
    </x-ui.card>

    <x-ui.card title="Chỉ số điều hành">
        <div class="operator-form-grid">
            <x-ui.field-value
                label="Tiến độ"
                :value="$progress['availability'] === Health::AVAILABLE
                    ? ((int) round((float) $progress['value'])) . '% (' . ($basisLabels[$progress['basis']] ?? '') . ')'
                    : 'Chưa có dữ liệu'"
            />
            <x-ui.field-value label="Thời hạn">
                @if ($health['schedule']['delay']['state'] === \App\Services\ProjectDelayStatus::STATE_COMPLETED)
                    Đã hoàn thành
                @else
                    @include('projects._delay-badge', ['delay' => $health['schedule']['delay']])
                @endif
            </x-ui.field-value>
            <x-ui.field-value
                label="Mốc"
                :value="$milestones['availability'] === Health::AVAILABLE
                    ? $milestones['completed'] . '/' . $milestones['total'] . ' hoàn thành · ' . $milestones['overdue'] . ' quá hạn'
                    : 'Chưa có dữ liệu'"
            />
            <x-ui.field-value
                label="Cần chú ý"
                :value="$attention['blocked_tasks'] . ' việc bị chặn · ' . $attention['overdue_tasks'] . ' việc quá hạn · ' . $attention['blocked_design_items'] . ' hạng mục thiết kế bị chặn'"
            />
        </div>
    </x-ui.card>

    <x-ui.card title="Nhóm công việc ({{ $oppm['workstream_total'] }})">
        @if (empty($oppm['workstreams']))
            <p class="text-sm text-slate-500">Chưa có nhóm công việc (dự án chưa có hạng mục).</p>
            @if (!empty($oppm['task_summary']))
                <p class="mt-2 text-sm text-slate-700">
                    Công việc theo trạng thái:
                    @foreach ($oppm['task_summary'] as $status => $count)
                        <span class="mr-3">{{ $statusLabels[$status] ?? $status }}: {{ $count }}</span>
                    @endforeach
                </p>
            @endif
        @else
            <x-ui.data-table :headers="['Nhóm công việc', 'Trạng thái', 'Tiến độ', 'Thời gian', 'Chi phí KH / ghi nhận', 'Bị chặn / quá hạn']">
                @foreach ($oppm['workstreams'] as $group)
                    <tr>
                        <td class="font-medium text-slate-900">{{ $group['name'] }}</td>
                        <td><x-ui.status-badge :status="$group['status']" /></td>
                        <td class="text-sm">{{ (int) round($group['progress_percent']) }}%</td>
                        <td class="min-w-48 text-xs text-slate-600">
                            {{ $group['start_date'] ?? '—' }} → {{ $group['end_date'] ?? '—' }}
                            @if ($group['timeline'] !== null)
                                <div class="oppm-bar-track mt-1">
                                    <div class="oppm-bar" style="left: {{ $group['timeline']['offset'] }}%; width: {{ $group['timeline']['width'] }}%"></div>
                                </div>
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">
                            {{ $group['planned_cost'] !== null ? number_format((float) $group['planned_cost'], 0, ',', '.') : '—' }}
                            /
                            {{ $group['actual_cost'] !== null ? number_format((float) $group['actual_cost'], 0, ',', '.') : '—' }}
                        </td>
                        <td class="text-sm">{{ $group['blocked_tasks'] }} / {{ $group['overdue_tasks'] }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
            @if ($oppm['workstream_total'] > count($oppm['workstreams']))
                <p class="mt-2 text-xs text-slate-500">Hiển thị {{ count($oppm['workstreams']) }}/{{ $oppm['workstream_total'] }} nhóm.</p>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card title="Vướng mắc và trễ hạn">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Việc bị chặn ({{ $oppm['blocked_tasks']['total'] }})</h3>
                @forelse ($oppm['blocked_tasks']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        <a href="/app/tasks/{{ $row['id'] }}" class="operator-link">{{ $row['name'] }}</a>
                        <div class="text-xs text-slate-500">{{ $row['note'] ?? '—' }} · {{ $row['by'] ?? '—' }} · từ {{ $row['since'] ?? '—' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Việc quá hạn ({{ $oppm['overdue_tasks']['total'] }})</h3>
                @forelse ($oppm['overdue_tasks']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        <a href="/app/tasks/{{ $row['id'] }}" class="operator-link">{{ $row['name'] }}</a>
                        <div class="text-xs text-slate-500">Hạn {{ $row['due'] ?? '—' }} · {{ $row['assignee'] ?? 'chưa giao' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Hạng mục thiết kế bị chặn ({{ $oppm['blocked_design_items']['total'] }})</h3>
                @forelse ($oppm['blocked_design_items']['rows'] as $row)
                    <div class="border-b border-slate-100 py-1 text-sm">
                        {{ $row['name'] }}
                        <div class="text-xs text-slate-500">{{ $row['note'] ?? '—' }} · {{ $row['by'] ?? '—' }} · từ {{ $row['since'] ?? '—' }}</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Không có.</p>
                @endforelse
            </div>
        </div>
    </x-ui.card>

    <x-ui.card title="Hợp đồng và tài chính">
        <div class="operator-form-grid">
            <x-ui.field-value label="Ngân sách" :value="$money($finance['budget'])" />
            <x-ui.field-value label="Giá trị hợp đồng" :value="$money($finance['contract_value'])" />
            <x-ui.field-value label="Đã thu" :value="$money($finance['cash_received'])" />
            <x-ui.field-value label="Chi phí ghi nhận (không phải tiền đã chi)" :value="$money($finance['cost_incurred'])" />
        </div>
        @if ($canViewTreasury && $finance['treasury']['availability'] === Health::AVAILABLE)
            <div class="mt-3 operator-form-grid">
                <x-ui.field-value label="Ngân quỹ dự án — đang giữ" :value="number_format((float) $finance['treasury']['held_total'], 0, ',', '.')" />
                <x-ui.field-value label="Ngân quỹ dự án — chi đã ghi sổ" :value="number_format((float) $finance['treasury']['posted_expenses'], 0, ',', '.')" />
            </div>
            <div class="oppm-no-print mt-2">
                <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Xem Ngân quỹ</x-ui.button-link>
            </div>
        @endif
    </x-ui.card>
@endsection

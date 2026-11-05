@extends('layouts.operator')

@section('title', $project->name . ' — So sánh kỳ OPPM')
@section('page_title', 'So sánh kỳ OPPM')

@section('content')
    {{-- GAP-110 K-1: this snapshot against the previous one, from stored numbers only. --}}
    @php
        $num = static fn ($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
        $delta = static fn ($d): string => $d === null ? '—' : (($d > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $d, 2, ',', '.'), '0'), ','));
        $delayLabels = [
            \App\Services\ProjectDelayStatus::STATE_LATE => 'Trễ',
            \App\Services\ProjectDelayStatus::STATE_FORECAST_LATE => 'Dự kiến trễ',
            \App\Services\ProjectDelayStatus::STATE_ON_TRACK => 'Đúng tiến độ',
            \App\Services\ProjectDelayStatus::STATE_NO_BASELINE => 'Chưa chốt KH',
            \App\Services\ProjectDelayStatus::STATE_COMPLETED => 'Đã hoàn thành',
        ];
        $delayText = static fn ($d): string => $d === null ? '—' : (($delayLabels[$d['state'] ?? ''] ?? ($d['state'] ?? '—')) . (($d['days_late'] ?? null) ? ' ' . $d['days_late'] . ' ngày' : ''));
        $rows = [
            'Tiến độ (%)' => $diff['progress'],
            'Việc bị chặn' => $diff['attention']['blocked_tasks'],
            'Việc quá hạn' => $diff['attention']['overdue_tasks'],
            'Hạng mục thiết kế bị chặn' => $diff['attention']['blocked_design_items'],
            'Quyết định đang chờ' => $diff['decisions_open'],
            'Ngân sách' => $diff['finance']['budget'],
            'Giá trị hợp đồng' => $diff['finance']['contract_value'],
            'Đã thu' => $diff['finance']['cash_received'],
            'Chi phí ghi nhận' => $diff['finance']['cost_incurred'],
        ];
    @endphp
    <x-ui.page-header :title="'So sánh kỳ — ' . $project->name" :description="'Kỳ ' . $diff['current']['label'] . ($diff['previous'] ? ' so với ' . $diff['previous']['label'] : ' (chưa có kỳ trước)')">
        <x-ui.button-link :href="route('app.projects.oppm.snapshots.show', [$project->id, $diff['current']['id']])" variant="secondary">Xem bản chốt</x-ui.button-link>
        <x-ui.button-link :href="route('app.projects.oppm.snapshots', $project->id)" variant="secondary">Lịch sử báo cáo</x-ui.button-link>
    </x-ui.page-header>

    @if ($diff['previous'] === null)
        <x-ui.card><p class="p-4 text-sm text-slate-600">Đây là bản chốt đầu tiên của dự án, chưa có kỳ trước để so sánh.</p></x-ui.card>
    @else
        <x-ui.card title="Chỉ số">
            <x-ui.data-table :headers="['Chỉ số', $diff['previous']['label'], $diff['current']['label'], 'Chênh lệch']">
                @foreach ($rows as $label => $pair)
                    <tr>
                        <td class="text-sm">{{ $label }}</td>
                        <td class="text-sm">{{ $num($pair['before']) }}</td>
                        <td class="text-sm">{{ $num($pair['after']) }}</td>
                        <td class="text-sm font-semibold">{{ $delta($pair['delta']) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="text-sm">Thời hạn</td>
                    <td class="text-sm">{{ $delayText($diff['delay']['before']) }}</td>
                    <td class="text-sm">{{ $delayText($diff['delay']['after']) }}</td>
                    <td></td>
                </tr>
            </x-ui.data-table>
        </x-ui.card>
        <x-ui.card title="Nhóm công việc (% hoàn thành)">
            @if ($diff['workstreams'] === [])
                <p class="p-4 text-sm text-slate-500">Không có nhóm công việc.</p>
            @else
                <x-ui.data-table :headers="['Nhóm công việc', $diff['previous']['label'], $diff['current']['label'], 'Chênh lệch']">
                    @foreach ($diff['workstreams'] as $group)
                        <tr>
                            <td class="text-sm">{{ $group['name'] }}</td>
                            <td class="text-sm">{{ $num($group['before']) }}</td>
                            <td class="text-sm">{{ $num($group['after']) }}</td>
                            <td class="text-sm font-semibold">{{ $delta($group['delta']) }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>
    @endif
@endsection

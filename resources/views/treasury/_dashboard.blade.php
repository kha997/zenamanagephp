{{-- GAP-097 S6: project dashboard — alerts, cards, wallet table. --}}
@php
    $alertLabels = [
        'unreconciled_funding' => 'Tiền nhận chưa đối soát quá 30 ngày',
        'pending_approvals' => 'Chi phí / tạm ứng đang chờ duyệt',
        'pending_settlements' => 'Đề nghị quyết toán tạm ứng chờ duyệt',
        'overdue_advances' => 'Tạm ứng còn nợ trên 90 ngày',
        'negative_wallets' => 'Ví đang âm',
        'open_routes' => 'Tuyến trung gian chưa về đủ',
    ];
    $cards = $dashboard['cards'];
@endphp

<x-ui.card title="Tổng quan ngân quỹ">
    <div class="space-y-4 p-4">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('operator.treasury.projects.reports.index', $project->id) }}" class="operator-button operator-button-secondary" data-testid="treasury-reports-link">Báo cáo ngân quỹ</a>
            <a href="{{ route('operator.treasury.projects.timeline', $project->id) }}" class="operator-button operator-button-secondary" data-testid="treasury-timeline-link">Dòng thời gian</a>
        </div>

        @if ($dashboard['alerts'] === [])
            <p class="text-sm text-emerald-700" data-testid="treasury-no-alerts">Không có cảnh báo.</p>
        @else
            <ul class="space-y-1" data-testid="treasury-alerts">
                @foreach ($dashboard['alerts'] as $alert)
                    <li class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-testid="treasury-alert-{{ $alert['code'] }}">
                        {{ $alertLabels[$alert['code']] ?? $alert['code'] }}: <strong>{{ $alert['count'] }}</strong>@if ((float) $alert['amount'] !== 0.0) — {{ $money($alert['amount']) }}@endif
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="operator-form-grid" data-testid="treasury-dashboard-cards">
            <x-ui.field-value label="Nhà đầu tư đã trả" :value="$money($cards['investor_funding'])" />
            <x-ui.field-value label="Trong đó về qua trung gian" :value="$money($cards['via_route_funding'])" />
            <x-ui.field-value label="Thanh toán hợp đồng đã về ví" :value="$money($cards['contract_payments_arrived'])" />
            <x-ui.field-value label="Vốn chủ góp" :value="$money($cards['owner_contribution'])" />
            <x-ui.field-value label="Chi phí đã ghi sổ" :value="$money($cards['expenses'])" />
            <x-ui.field-value label="Tiền dự án đang giữ" :value="$money($cards['held_total'])" />
            <x-ui.field-value label="Tiền nhận chưa đối soát" :value="$money($cards['unreconciled_funding'])" />
            <x-ui.field-value label="Đang chờ duyệt" :value="$cards['pending_approvals_count'] . ' khoản — ' . $money($cards['pending_approvals_amount'])" />
            <x-ui.field-value label="Tạm ứng còn nợ" :value="$money($cards['outstanding_advances'])" />
        </div>

        <x-ui.data-table :headers="['Ví', 'Người giữ', 'Số dư', 'Chưa đối soát', 'Tạm ứng còn nợ', 'Phát sinh cuối']">
            @foreach ($dashboard['wallets'] as $wallet)
                <tr data-testid="treasury-dashboard-wallet-row">
                    <td class="text-sm font-medium">{{ $wallet['name'] }}</td>
                    <td class="text-sm text-slate-600">{{ $wallet['custodian_name'] ?? '—' }}</td>
                    <td class="text-sm font-semibold @if ((float) $wallet['balance'] < 0) text-rose-700 @endif">{{ $money($wallet['balance']) }}</td>
                    <td class="text-sm">{{ $money($wallet['unreconciled']) }}</td>
                    <td class="text-sm">{{ $money($wallet['outstanding_advances']) }}</td>
                    <td class="text-sm text-slate-600">{{ $wallet['last_activity_at'] ? \Illuminate\Support\Carbon::parse($wallet['last_activity_at'])->format('d/m/Y') : '—' }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </div>
</x-ui.card>

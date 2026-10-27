@extends('layouts.operator')

@section('title', 'Tạm ứng — ' . $project->name)
@section('page_title', 'Tạm ứng')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $statusLabels = [
            'draft' => 'Nháp', 'submitted' => 'Chờ duyệt', 'rejected' => 'Bị từ chối',
            'posted_unreconciled' => 'Đã chi — chưa đối soát', 'posted_reconciled' => 'Đã chi — đã đối soát', 'reversed' => 'Đã đảo',
        ];
        $eventLabels = [
            'submitted' => 'Gửi duyệt', 'advance_approved' => 'Duyệt tạm ứng', 'posted' => 'Chi tiền', 'rejected' => 'Từ chối',
            'settlement_requested' => 'Đề nghị quyết toán', 'settlement_withdrawn' => 'Rút đề nghị quyết toán',
            'settlement_rejected' => 'Từ chối quyết toán', 'settlement_approved' => 'Duyệt quyết toán', 'settlement_removed' => 'Gỡ quyết toán',
        ];
        $bucketLabels = ['under_30' => 'dưới 30 ngày', '30_60' => '30–60 ngày', '60_90' => '60–90 ngày', 'over_90' => 'trên 90 ngày'];
        $row = $advance['advance'];
        $posted = in_array($advance['status'], ['posted_unreconciled', 'posted_reconciled'], true);
        $outstanding = $row !== null ? (float) $row['outstanding'] : 0.0;
        $request = $advance['open_request'];
        $cost = fn (array $line) => $costLabels[$line['cost_source_type'] . ':' . $line['cost_source_id']] ?? $line['cost_source_id'];
        $activeSettlements = collect($advance['settlements'])->filter(fn ($s) => $s['direction'] === 'apply' && !$s['removed']);
    @endphp

    <x-ui.page-header
        title="Tạm ứng — {{ $parties[$advance['destination_party_id']] ?? '—' }} {{ $money($advance['amount']) }}"
        description="{{ $project->name }}. Quyết toán bằng chi phí hoặc nộp lại tiền thừa để giảm số còn nợ."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')

    <x-ui.card title="Tình trạng">
        <div class="operator-form-grid p-4" data-testid="treasury-advance-summary">
            <x-ui.field-value label="Trạng thái" :value="$statusLabels[$advance['status']] ?? $advance['status']" />
            <x-ui.field-value label="Chi từ ví" :value="$wallets->firstWhere('id', $advance['source_wallet_id'])?->name ?? '—'" />
            @if ($row !== null)
                <x-ui.field-value label="Đã quyết toán bằng chi phí" :value="$money($row['settled'])" />
                <x-ui.field-value label="Đã nộp lại" :value="$money($row['returned'])" />
                <x-ui.field-value label="Còn nợ" :value="$money($row['outstanding'])" />
                <x-ui.field-value label="Tuổi nợ" :value="$row['days'] . ' ngày (' . $bucketLabels[$row['bucket']] . ')'" />
            @endif
            <x-ui.field-value label="Ghi chú" :value="$advance['description'] ?? '—'" />
        </div>
        @if ($advance['status'] === 'reversed')
            <p class="px-4 pb-4 text-sm text-slate-600">Tạm ứng đã bị đảo; không còn nợ.</p>
        @elseif ($posted && ($activeSettlements->isNotEmpty() || (float) ($row['returned'] ?? 0) > 0))
            <p class="px-4 pb-4 text-sm text-amber-700" data-testid="treasury-advance-reverse-hint">Muốn đảo tạm ứng này: gỡ hết quyết toán và đảo các lần nộp lại trước.</p>
        @endif
    </x-ui.card>

    @if ($request !== null)
        <x-ui.card title="Đề nghị quyết toán đang chờ duyệt">
            <div class="space-y-3 p-4" data-testid="treasury-advance-open-request">
                <p class="text-sm">{{ $users[$request['actor_id']] ?? '—' }} đề nghị quyết toán <strong>{{ $money($request['amount']) }}</strong>@if ($request['note']) — {{ $request['note'] }}@endif</p>
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($request['allocations'] as $line)
                        <li>{{ $cost($line) }}: {{ $money($line['amount']) }}</li>
                    @endforeach
                </ul>
                <div class="flex flex-wrap gap-2">
                    @if ($can['approve'])
                        <form method="POST" action="{{ route('operator.treasury.projects.advances.settlement-request.action', [$project->id, $advance['id'], 'approve']) }}" data-testid="treasury-settlement-approve-form">@csrf<button type="submit" class="operator-button operator-button-primary">Duyệt quyết toán</button></form>
                        <details>
                            <summary class="operator-button operator-button-inline">Từ chối</summary>
                            <form method="POST" action="{{ route('operator.treasury.projects.advances.settlement-request.action', [$project->id, $advance['id'], 'reject']) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="note" class="operator-input" placeholder="Lý do từ chối" required>
                                <button type="submit" class="operator-button operator-button-primary">Xác nhận từ chối</button>
                            </form>
                        </details>
                    @endif
                    @if ($can['request'] && (string) $request['actor_id'] === (string) auth()->id())
                        <form method="POST" action="{{ route('operator.treasury.projects.advances.settlement-request.action', [$project->id, $advance['id'], 'withdraw']) }}">@csrf<button type="submit" class="operator-button operator-button-inline">Rút đề nghị</button></form>
                    @endif
                </div>
            </div>
        </x-ui.card>
    @elseif ($posted && $outstanding > 0 && $can['request'])
        <x-ui.card title="Đề nghị quyết toán bằng chi phí">
            <form method="POST" action="{{ route('operator.treasury.projects.advances.settlement-request.store', [$project->id, $advance['id']]) }}" class="space-y-5 p-4" data-testid="treasury-settlement-request-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="s_amount">Số tiền quyết toán (₫) <span class="text-rose-600">*</span></label>
                        <input id="s_amount" name="amount" type="number" min="0.01" step="0.01" class="operator-input" value="{{ old('amount') }}" required>
                    </div>
                    <div class="operator-field">
                        <label for="s_note">Ghi chú</label>
                        <input id="s_note" name="note" type="text" maxlength="2000" class="operator-input" value="{{ old('note') }}" placeholder="Hoá đơn, chứng từ kèm theo…">
                    </div>
                </div>
                <p class="text-sm text-slate-600">Gắn vào chi phí (tổng phải bằng số tiền quyết toán, không vượt số còn nợ {{ $money($outstanding) }}):</p>
                @for ($i = 0; $i < 3; $i++)
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="s_line_{{ $i }}">Chi phí {{ $i + 1 }}</label>
                            <select id="s_line_{{ $i }}" name="lines[{{ $i }}][cost]" class="operator-input">
                                <option value="">—</option>
                                @foreach ($payables as $payable)
                                    @if ((float) $payable['remaining'] > 0)
                                        <option value="{{ $payable['type'] }}:{{ $payable['id'] }}" @selected(old("lines.$i.cost") === $payable['type'] . ':' . $payable['id'])>{{ $payable['label'] }} — còn {{ $money($payable['remaining']) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="s_line_amount_{{ $i }}">Số tiền gắn</label>
                            <input id="s_line_amount_{{ $i }}" name="lines[{{ $i }}][amount]" type="number" min="0" step="0.01" class="operator-input" value="{{ old("lines.$i.amount") }}">
                        </div>
                    </div>
                @endfor
                <button type="submit" class="operator-button operator-button-primary">Gửi đề nghị quyết toán</button>
            </form>
        </x-ui.card>
    @endif

    @if ($posted && $outstanding > 0 && $can['reverse'])
        <x-ui.card title="Nộp lại tiền thừa">
            <form method="POST" action="{{ route('operator.treasury.projects.advances.returns.store', [$project->id, $advance['id']]) }}" class="space-y-5 p-4" data-testid="treasury-advance-return-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="ret_wallet">Nộp vào ví <span class="text-rose-600">*</span></label>
                        <select id="ret_wallet" name="destination_wallet_id" class="operator-input" required>
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('destination_wallet_id') === $wallet->id)>{{ $wallet->name }} ({{ $money($walletBalances[$wallet->id] ?? 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    @include('treasury._money-fields', ['prefix' => 'ret'])
                </div>
                <button type="submit" class="operator-button operator-button-primary">Ghi nộp lại</button>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Quyết toán và nộp lại">
        @if ($advance['settlements'] === [])
            <x-ui.empty-state title="Chưa có quyết toán" description="Quyết toán bằng chi phí và các lần nộp lại tiền hiện ở đây." />
        @else
            <x-ui.data-table :headers="['Ngày', 'Loại', 'Số tiền', 'Chi phí', 'Thao tác']">
                @foreach ($advance['settlements'] as $settlement)
                    <tr data-testid="treasury-advance-settlement-row">
                        <td class="text-sm">{{ \Illuminate\Support\Carbon::parse($settlement['created_at'])->format('d/m/Y') }}</td>
                        <td class="text-sm">
                            {{ $settlement['settlement_type'] === 'approved_expense' ? 'Quyết toán bằng chi phí' : 'Nộp lại tiền' }}{{ $settlement['direction'] === 'reverse' ? ' — gỡ / đảo' : '' }}
                            @if ($settlement['removed']) <span class="ml-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">Đã gỡ</span>@endif
                        </td>
                        <td class="text-sm font-semibold">{{ $money($settlement['amount']) }}</td>
                        <td class="text-sm text-slate-600">{{ collect($settlement['allocations'])->map(fn ($a) => $cost($a) . ': ' . $money($a['amount']))->implode('; ') ?: '—' }}</td>
                        <td class="text-sm">
                            @if ($can['reverse'] && $settlement['settlement_type'] === 'approved_expense' && $settlement['direction'] === 'apply' && !$settlement['removed'])
                                <details>
                                    <summary class="operator-button operator-button-inline">Gỡ quyết toán</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.advance-settlements.remove', [$project->id, $settlement['id']]) }}" class="mt-2 space-y-2" data-testid="treasury-settlement-remove-form">
                                        @csrf
                                        <input type="text" name="reason" class="operator-input" placeholder="Lý do gỡ" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận gỡ</button>
                                    </form>
                                </details>
                            @elseif ($settlement['settlement_type'] === 'cash_return' && $settlement['direction'] === 'apply' && !$settlement['removed'])
                                <span class="text-xs text-slate-500">Gỡ bằng "Đảo" trong sổ giao dịch</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Nhật ký duyệt">
        <x-ui.data-table :headers="['Thời điểm', 'Bước', 'Người thực hiện', 'Ghi chú']">
            @foreach ($advance['events'] as $event)
                <tr data-testid="treasury-advance-event-row">
                    <td class="text-sm">{{ \Illuminate\Support\Carbon::parse($event['created_at'])->format('d/m/Y H:i') }}</td>
                    <td class="text-sm">{{ $eventLabels[$event['event']] ?? $event['event'] }}@if (data_get($event, 'context.approval_mode') === 'self_approval') <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Tự duyệt</span>@endif</td>
                    <td class="text-sm text-slate-600">{{ $users[$event['actor_id']] ?? '—' }}</td>
                    <td class="text-sm text-slate-600">{{ $event['note'] ?? '—' }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </x-ui.card>
@endsection

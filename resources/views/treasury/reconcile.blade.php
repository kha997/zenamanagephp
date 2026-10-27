@extends('layouts.operator')

@section('title', 'Đối soát — ' . $wallet->name)
@section('page_title', 'Đối soát ví')

@section('content')
    <x-ui.page-header
        title="Đối soát — {{ $wallet->name }}"
        description="{{ $project->name }}. Tick các giao dịch khớp với sao kê ngân hàng, kiểm quỹ tiền mặt hoặc chứng từ."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $typeLabels = [
            'funding' => 'Tiền nhận', 'owner_contribution' => 'Góp vốn chủ', 'internal_transfer' => 'Chuyển ví',
            'adjustment' => 'Điều chỉnh', 'reversal' => 'Bút toán đảo', 'expense' => 'Chi phí',
        ];
        $reconciliationTypeLabels = ['bank_statement' => 'Sao kê ngân hàng', 'cash_count' => 'Kiểm quỹ tiền mặt', 'voucher' => 'Chứng từ khác'];
    @endphp

    @include('treasury._errors')

    <x-ui.card title="Số dư ví">
        <div class="operator-form-grid p-4" data-testid="treasury-reconcile-balances">
            <x-ui.field-value label="Số dư" :value="$money($balances['balance'])" />
            <x-ui.field-value label="Đã đối soát" :value="$money($balances['reconciled'])" />
            <x-ui.field-value label="Chưa đối soát" :value="$money($balances['unreconciled'])" />
        </div>
        <p class="px-4 pb-4 text-sm text-slate-600">Chuyển ví chỉ thành "Đã đối soát" khi cả ví đi và ví đến đều đã đối soát giao dịch đó.</p>
    </x-ui.card>

    <x-ui.card title="Giao dịch chưa đối soát">
        @if ($entries === [])
            <x-ui.empty-state title="Không còn giao dịch chưa đối soát" description="Mọi giao dịch của ví này đã được đối soát." />
        @elseif ($canReconcile)
            <form method="POST" action="{{ route('operator.treasury.projects.wallets.reconcile.store', [$project->id, $wallet->id]) }}" class="space-y-5" data-testid="treasury-reconcile-form">
                @csrf
                <x-ui.data-table :headers="['Chọn', 'Ngày', 'Loại', 'Tham chiếu', 'Diễn giải', 'Vào / ra', 'Số tiền']">
                    @foreach ($entries as $entry)
                        <tr data-testid="treasury-unreconciled-row">
                            <td><input type="checkbox" name="ledger_entry_ids[]" value="{{ $entry['ledger_entry_id'] }}" @checked(in_array($entry['ledger_entry_id'], (array) old('ledger_entry_ids', []), true)) aria-label="Chọn giao dịch"></td>
                            <td class="text-sm">{{ $entry['transaction_date'] ? \Illuminate\Support\Carbon::parse($entry['transaction_date'])->format('d/m/Y') : '—' }}</td>
                            <td class="text-sm">{{ $typeLabels[$entry['document_type']] ?? $entry['document_type'] }}@if ($entry['document_status'] === 'reversed') <span class="text-xs text-slate-500">(đã đảo)</span>@endif</td>
                            <td class="text-sm text-slate-600">{{ $entry['reference'] ?? '—' }}</td>
                            <td class="text-sm text-slate-600">{{ $entry['description'] ?? '—' }}</td>
                            <td class="text-sm">{{ $entry['direction'] === 'credit' ? 'Vào' : 'Ra' }}</td>
                            <td class="text-sm font-semibold {{ $entry['direction'] === 'credit' ? 'text-emerald-700' : 'text-rose-600' }}">{{ $entry['direction'] === 'credit' ? '+' : '−' }}{{ $money($entry['amount']) }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="r_type">Đối soát theo <span class="text-rose-600">*</span></label>
                        <select id="r_type" name="reconciliation_type" class="operator-input" required>
                            @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('reconciliation_type') === $type)>{{ $reconciliationTypeLabels[$type] ?? $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="r_reference">Số tham chiếu</label>
                        <input id="r_reference" type="text" name="external_reference" class="operator-input" value="{{ old('external_reference') }}" maxlength="255">
                        <p class="text-xs text-slate-500">Bắt buộc với sao kê ngân hàng và chứng từ; không bắt buộc với kiểm quỹ.</p>
                    </div>
                    <div class="operator-field">
                        <label for="r_date">Ngày đối soát <span class="text-rose-600">*</span></label>
                        <input id="r_date" type="date" name="reconciled_at" class="operator-input" value="{{ old('reconciled_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <button type="submit" class="operator-button operator-button-primary">Đối soát các giao dịch đã chọn</button>
            </form>
        @else
            <x-ui.data-table :headers="['Ngày', 'Loại', 'Tham chiếu', 'Diễn giải', 'Vào / ra', 'Số tiền']">
                @foreach ($entries as $entry)
                    <tr data-testid="treasury-unreconciled-row">
                        <td class="text-sm">{{ $entry['transaction_date'] ? \Illuminate\Support\Carbon::parse($entry['transaction_date'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-sm">{{ $typeLabels[$entry['document_type']] ?? $entry['document_type'] }}</td>
                        <td class="text-sm text-slate-600">{{ $entry['reference'] ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $entry['description'] ?? '—' }}</td>
                        <td class="text-sm">{{ $entry['direction'] === 'credit' ? 'Vào' : 'Ra' }}</td>
                        <td class="text-sm font-semibold">{{ $money($entry['amount']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Lịch sử đối soát">
        @if ($history === [])
            <x-ui.empty-state title="Chưa có lần đối soát nào" description="Các lần đối soát của ví này sẽ hiện ở đây." />
        @else
            <div class="space-y-6 p-4">
                @foreach ($history as $rec)
                    <div class="space-y-2" data-testid="treasury-reconciliation-history">
                        <div class="flex flex-wrap items-center gap-3 text-sm">
                            <span class="font-semibold">{{ $rec['reconciled_at'] ? \Illuminate\Support\Carbon::parse($rec['reconciled_at'])->format('d/m/Y') : '—' }}</span>
                            <span>{{ $reconciliationTypeLabels[$rec['reconciliation_type']] ?? $rec['reconciliation_type'] }}</span>
                            <span class="text-slate-600">Tham chiếu: {{ $rec['external_reference'] ?? '—' }}</span>
                            <span class="text-slate-600">Người đối soát: {{ $rec['reconciled_by'] ?? '—' }}</span>
                            @if ($canReconcile && $rec['active'])
                                <details>
                                    <summary class="operator-button operator-button-inline">Gỡ cả lần đối soát</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.reconciliations.undo', [$project->id, $rec['id']]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="text" name="reason" class="operator-input" placeholder="Lý do gỡ" maxlength="2000" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận gỡ</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                        <x-ui.data-table :headers="['Ngày', 'Loại', 'Tham chiếu', 'Số tiền', 'Trạng thái', 'Thao tác']">
                            @foreach ($rec['lines'] as $line)
                                <tr data-testid="treasury-reconciliation-line">
                                    <td class="text-sm">{{ $line['transaction_date'] ? \Illuminate\Support\Carbon::parse($line['transaction_date'])->format('d/m/Y') : '—' }}</td>
                                    <td class="text-sm">{{ $typeLabels[$line['document_type']] ?? $line['document_type'] }}</td>
                                    <td class="text-sm text-slate-600">{{ $line['reference'] ?? '—' }}</td>
                                    <td class="text-sm font-semibold">{{ $line['direction'] === 'credit' ? '+' : '−' }}{{ $money($line['amount']) }}</td>
                                    <td class="text-sm">
                                        @if ($line['active'])
                                            Đang hiệu lực
                                        @else
                                            Đã gỡ{{ $line['undone_by'] ? ' bởi ' . $line['undone_by'] : '' }} — lý do: {{ $line['undo_reason'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-sm">
                                        @if ($canReconcile && $line['active'])
                                            <details>
                                                <summary class="operator-button operator-button-inline">Gỡ</summary>
                                                <form method="POST" action="{{ route('operator.treasury.projects.reconciliation-entries.undo', [$project->id, $line['id']]) }}" class="mt-2 space-y-2">
                                                    @csrf
                                                    <input type="text" name="reason" class="operator-input" placeholder="Lý do gỡ" maxlength="2000" required>
                                                    <button type="submit" class="operator-button operator-button-primary">Xác nhận gỡ</button>
                                                </form>
                                            </details>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.data-table>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($historyPage > 1 || $historyHasNextPage)
            {{-- GAP-068: plain links, 50 reconciliations per page. --}}
            <div class="flex gap-2 px-4 pb-4" data-testid="treasury-reconciliation-history-pages">
                @if ($historyPage > 1)
                    <a href="{{ route('operator.treasury.projects.wallets.reconcile', [$project->id, $wallet->id]) }}?page={{ $historyPage - 1 }}" class="operator-button operator-button-inline">Trang trước</a>
                @endif
                <span class="text-sm text-slate-600">Trang {{ $historyPage }}</span>
                @if ($historyHasNextPage)
                    <a href="{{ route('operator.treasury.projects.wallets.reconcile', [$project->id, $wallet->id]) }}?page={{ $historyPage + 1 }}" class="operator-button operator-button-inline">Trang sau</a>
                @endif
            </div>
        @endif
    </x-ui.card>
@endsection

@extends('layouts.operator')

@section('title', 'Ngân quỹ — ' . $project->name)
@section('page_title', 'Ngân quỹ dự án')

@section('content')
    <x-ui.page-header
        title="Ngân quỹ — {{ $project->name }}"
        description="Các ví đang giữ tiền của dự án (tài khoản, tiền mặt, người giữ tiền)."
    >
        <x-ui.button-link :href="route('operator.treasury.index')" variant="secondary">Tất cả dự án</x-ui.button-link>
    </x-ui.page-header>

    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $typeLabels = [
            'funding' => 'Tiền nhận', 'owner_contribution' => 'Góp vốn chủ', 'internal_transfer' => 'Chuyển ví',
            'adjustment' => 'Điều chỉnh', 'reversal' => 'Bút toán đảo', 'expense' => 'Chi phí',
        ];
        $statusLabels = [
            'posted_unreconciled' => 'Đã ghi sổ — chưa đối soát', 'posted_reconciled' => 'Đã đối soát', 'reversed' => 'Đã đảo',
            'draft' => 'Nháp', 'submitted' => 'Chờ duyệt', 'rejected' => 'Bị từ chối',
        ];
        $categoryLabels = ['labor' => 'Nhân công', 'subcontractor' => 'Thầu phụ', 'design_outsource' => 'Thiết kế thuê ngoài', 'misc' => 'Khác'];
        $endpoint = fn ($wallet, $party) => $wallet?->name ?? $party?->name ?? '—';
    @endphp

    @if (session('treasury_duplicate'))
        <div class="operator-error-list" data-testid="treasury-duplicate-warning">
            {{ session('treasury_duplicate') }} Đánh dấu "Vẫn ghi" trong biểu mẫu nếu đây không phải khai trùng.
        </div>
    @endif
    @include('treasury._errors')

    <x-ui.card title="Số dư">
        <div class="operator-form-grid p-4" data-testid="treasury-balances">
            <x-ui.field-value label="Tổng tiền dự án đang giữ" :value="$money($summary['held_total'])" />
            <x-ui.field-value label="Nhà đầu tư đã nộp" :value="$money($summary['investor_funding'])" />
            <x-ui.field-value label="Vốn chủ góp" :value="$money($summary['owner_contribution'])" />
            <x-ui.field-value label="Đã chi (chi phí)" :value="$money($summary['expenses'])" />
            <x-ui.field-value label="Trong đó tự duyệt" :value="$money($summary['self_approved_expenses'])" />
        </div>
    </x-ui.card>

    <x-ui.card title="Ví của dự án">
        @if ($wallets->isEmpty())
            <x-ui.empty-state title="Chưa có ví" description="Thêm ví để chuẩn bị ghi nhận tiền của dự án." />
        @else
            <x-ui.data-table :headers="['Tên ví', 'Loại', 'Người giữ', 'Số dư', 'Đã đối soát', 'Chưa đối soát', 'Thao tác']">
                @foreach ($wallets as $wallet)
                    <tr data-testid="treasury-wallet-row">
                        <td class="font-medium text-slate-900">{{ $wallet->name }}</td>
                        <td class="text-sm text-slate-600">{{ $walletTypes[$wallet->wallet_type] ?? $wallet->wallet_type }}</td>
                        <td class="text-sm text-slate-600">{{ $wallet->custodianParty?->name ?? '—' }}</td>
                        @php $balance = $summary['wallets'][$wallet->id] ?? '0.00'; @endphp
                        <td class="text-sm font-semibold {{ (float) $balance < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $money($balance) }}</td>
                        {{-- GAP-067 S4a: reconciled / unreconciled split of the same balance. --}}
                        <td class="text-sm text-emerald-700" data-testid="treasury-wallet-reconciled">{{ $money($reconciliationSummary[$wallet->id]['reconciled'] ?? '0.00') }}</td>
                        <td class="text-sm text-amber-700" data-testid="treasury-wallet-unreconciled">{{ $money($reconciliationSummary[$wallet->id]['unreconciled'] ?? '0.00') }}</td>
                        <td class="flex gap-2">
                            <a href="{{ route('operator.treasury.projects.wallets.reconcile', [$project->id, $wallet->id]) }}" class="operator-button operator-button-inline" data-testid="treasury-wallet-reconcile-link">Đối soát</a>
                            @if ($canManageWallets)
                                <a href="{{ route('operator.treasury.projects.wallets.edit', [$project->id, $wallet->id]) }}" class="operator-button operator-button-inline">Sửa</a>
                                <form method="POST" action="{{ route('operator.treasury.projects.wallets.destroy', [$project->id, $wallet->id]) }}" onsubmit="return confirm('Xoá ví này?')">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-inline">Xoá</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($can['declare'])
        <x-ui.card title="Khai báo tiền nhận">
            <form method="POST" action="{{ route('operator.treasury.projects.funding.store', $project->id) }}" class="space-y-5" data-testid="treasury-funding-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="f_type">Loại <span class="text-rose-600">*</span></label>
                        <select id="f_type" name="document_type" class="operator-input" required>
                            <option value="funding" @selected(old('document_type') === 'funding')>Tiền nhận (nhà đầu tư, bên trung gian…)</option>
                            <option value="owner_contribution" @selected(old('document_type') === 'owner_contribution')>Góp vốn chủ</option>
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="f_party">Nhận từ <span class="text-rose-600">*</span></label>
                        <select id="f_party" name="source_party_id" class="operator-input" required>
                            @foreach ($parties as $party)
                                <option value="{{ $party->id }}" @selected(old('source_party_id') === $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="f_wallet">Vào ví <span class="text-rose-600">*</span></label>
                        <select id="f_wallet" name="destination_wallet_id" class="operator-input" required>
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('destination_wallet_id') === $wallet->id)>{{ $wallet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('treasury._money-fields', ['prefix' => 'f'])
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirm_duplicate" value="1"> Vẫn ghi nếu hệ thống cảnh báo trùng</label>
                <button type="submit" class="operator-button operator-button-primary">Ghi nhận</button>
            </form>
        </x-ui.card>
    @endif

    @if ($can['transfer'] && $transferSources->isNotEmpty())
        <x-ui.card title="Chuyển ví">
            <form method="POST" action="{{ route('operator.treasury.projects.transfers.store', $project->id) }}" class="space-y-5" data-testid="treasury-transfer-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="t_source">Từ ví <span class="text-rose-600">*</span></label>
                        <select id="t_source" name="source_wallet_id" class="operator-input" required>
                            @foreach ($transferSources as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('source_wallet_id') === $wallet->id)>{{ $wallet->name }} ({{ $money($summary['wallets'][$wallet->id] ?? 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="t_destination">Đến ví <span class="text-rose-600">*</span></label>
                        <select id="t_destination" name="destination_wallet_id" class="operator-input" required>
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('destination_wallet_id') === $wallet->id)>{{ $wallet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('treasury._money-fields', ['prefix' => 't'])
                </div>
                <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
            </form>
        </x-ui.card>
    @endif

    @if ($can['adjust'])
        <x-ui.card title="Điều chỉnh số dư">
            <form method="POST" action="{{ route('operator.treasury.projects.adjustments.store', $project->id) }}" class="space-y-5" data-testid="treasury-adjustment-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="a_wallet">Ví <span class="text-rose-600">*</span></label>
                        <select id="a_wallet" name="wallet_id" class="operator-input" required>
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}">{{ $wallet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="a_direction">Hướng <span class="text-rose-600">*</span></label>
                        <select id="a_direction" name="direction" class="operator-input" required>
                            <option value="increase">Tăng (vd số dư đầu kỳ)</option>
                            <option value="decrease">Giảm (vd kiểm quỹ thiếu)</option>
                        </select>
                    </div>
                    @include('treasury._money-fields', ['prefix' => 'a', 'reasonRequired' => true])
                </div>
                <button type="submit" class="operator-button operator-button-primary">Ghi điều chỉnh</button>
            </form>
        </x-ui.card>
    @endif

    @if ($can['createExpense'] && $transferSources->isNotEmpty())
        <x-ui.card title="Tạo khoản chi">
            <form method="POST" action="{{ route('operator.treasury.projects.expenses.store', $project->id) }}" class="space-y-5" data-testid="treasury-expense-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="e_wallet">Chi từ ví <span class="text-rose-600">*</span></label>
                        <select id="e_wallet" name="source_wallet_id" class="operator-input" required>
                            @foreach ($transferSources as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('source_wallet_id') === $wallet->id)>{{ $wallet->name }} ({{ $money($summary['wallets'][$wallet->id] ?? 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="e_party">Trả cho <span class="text-rose-600">*</span></label>
                        <select id="e_party" name="destination_party_id" class="operator-input" required>
                            @foreach ($parties as $party)
                                <option value="{{ $party->id }}" @selected(old('destination_party_id') === $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('treasury._money-fields', ['prefix' => 'e'])
                </div>
                <p class="text-sm text-slate-600">Gắn khoản chi vào chi phí (tổng phải bằng số tiền chi):</p>
                @for ($i = 0; $i < 3; $i++)
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="e_line_{{ $i }}">Chi phí {{ $i + 1 }}</label>
                            <select id="e_line_{{ $i }}" name="lines[{{ $i }}][cost]" class="operator-input">
                                <option value="">—</option>
                                @foreach ($payables as $payable)
                                    @if ((float) $payable['remaining'] > 0)
                                        <option value="{{ $payable['type'] }}:{{ $payable['id'] }}" @selected(old("lines.$i.cost") === $payable['type'] . ':' . $payable['id'])>{{ $payable['label'] }} — còn {{ $money($payable['remaining']) }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="e_line_amount_{{ $i }}">Số tiền gắn</label>
                            <input id="e_line_amount_{{ $i }}" name="lines[{{ $i }}][amount]" type="number" min="0" step="0.01" class="operator-input" value="{{ old("lines.$i.amount") }}">
                        </div>
                    </div>
                @endfor
                @if ($contracts->isNotEmpty())
                    <p class="text-sm text-slate-600">Hoặc ghi một chi phí hợp đồng mới cùng lúc:</p>
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="e_new_contract">Hợp đồng</label>
                            <select id="e_new_contract" name="new_contract_id" class="operator-input">
                                <option value="">—</option>
                                @foreach ($contracts as $contract)
                                    <option value="{{ $contract->id }}" @selected(old('new_contract_id') === $contract->id)>{{ $contract->code }} — {{ $contract->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="e_new_category">Loại</label>
                            <select id="e_new_category" name="new_category" class="operator-input">
                                @foreach ($categoryLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('new_category') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="operator-field">
                            <label for="e_new_amount">Số tiền</label>
                            <input id="e_new_amount" name="new_amount" type="number" min="0" step="0.01" class="operator-input" value="{{ old('new_amount') }}">
                        </div>
                        <div class="operator-field">
                            <label for="e_new_description">Diễn giải chi phí</label>
                            <input id="e_new_description" name="new_description" type="text" maxlength="1000" class="operator-input" value="{{ old('new_description') }}">
                        </div>
                    </div>
                @endif
                <div class="flex gap-2">
                    <button type="submit" name="action" value="draft" class="operator-button operator-button-secondary">Lưu nháp</button>
                    @if ($can['submitExpense'])
                        <button type="submit" name="action" value="submit" class="operator-button operator-button-primary">Lưu &amp; gửi duyệt</button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    @endif

    <x-ui.card title="Khoản chi chờ xử lý">
        @if ($expenseList->isEmpty())
            <x-ui.empty-state title="Không có khoản chi nào đang chờ" description="Nháp, khoản chờ duyệt và khoản bị từ chối hiện ở đây." />
        @else
            <x-ui.data-table :headers="['Ngày', 'Chi từ ví', 'Trả cho', 'Số tiền', 'Trạng thái', 'Thao tác']">
                @foreach ($expenseList as $expense)
                    <tr data-testid="treasury-expense-row">
                        <td class="text-sm">{{ $expense->transaction_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $expense->sourceWallet?->name ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $expense->destinationParty?->name ?? '—' }}</td>
                        <td class="text-sm font-semibold">{{ $money($expense->amount) }}</td>
                        <td class="text-sm">{{ $statusLabels[$expense->status] ?? $expense->status }}</td>
                        <td class="text-sm">
                            @php $action = fn ($a) => route('operator.treasury.projects.expenses.action', [$project->id, $expense->id, $a]); @endphp
                            @if ($expense->status === 'draft' && $can['submitExpense'] && (string) $expense->created_by === (string) auth()->id())
                                <form method="POST" action="{{ $action('submit') }}">@csrf<button type="submit" class="operator-button operator-button-inline">Gửi duyệt</button></form>
                            @elseif ($expense->status === 'submitted' && $can['approveExpense'])
                                <form method="POST" action="{{ $action('approve') }}" class="inline">@csrf<button type="submit" class="operator-button operator-button-primary">Duyệt &amp; chi</button></form>
                                <details>
                                    <summary class="operator-button operator-button-inline">Từ chối</summary>
                                    <form method="POST" action="{{ $action('reject') }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="text" name="note" class="operator-input" placeholder="Lý do từ chối" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận từ chối</button>
                                    </form>
                                </details>
                            @elseif ($expense->status === 'rejected' && $can['createExpense'])
                                <form method="POST" action="{{ $action('copy') }}">@csrf<button type="submit" class="operator-button operator-button-inline">Sao chép thành nháp mới</button></form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Chi phí phải trả">
        @if ($payables === [])
            <x-ui.empty-state title="Chưa có chi phí" description="Chi phí hợp đồng và phiếu nhập vật tư của dự án hiện ở đây." />
        @else
            <x-ui.data-table :headers="['Chi phí', 'Phát sinh', 'Đã trả', 'Còn phải trả']">
                @foreach ($payables as $payable)
                    <tr data-testid="treasury-payable-row">
                        <td class="text-sm">{{ $payable['label'] }}</td>
                        <td class="text-sm">{{ $money($payable['incurred']) }}</td>
                        <td class="text-sm">{{ $money($payable['paid']) }}</td>
                        <td class="text-sm font-semibold">{{ $money($payable['remaining']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Sổ giao dịch">
        <form method="GET" action="{{ route('operator.treasury.projects.show', $project->id) }}" class="flex items-end gap-2 p-4" data-testid="treasury-register-filter">
            <div class="operator-field">
                <label for="register_status">Trạng thái</label>
                <select id="register_status" name="status" class="operator-input">
                    <option value="">Tất cả</option>
                    <option value="posted_reconciled" @selected($statusFilter === 'posted_reconciled')>Đã đối soát</option>
                    <option value="posted_unreconciled" @selected($statusFilter === 'posted_unreconciled')>Chưa đối soát</option>
                    <option value="reversed" @selected($statusFilter === 'reversed')>Đã đảo</option>
                </select>
            </div>
            <button type="submit" class="operator-button operator-button-inline">Lọc</button>
        </form>
        @if ($documents->isEmpty())
            <x-ui.empty-state title="Chưa có giao dịch" description="Các khoản tiền nhận, chuyển ví, điều chỉnh sẽ hiện ở đây." />
        @else
            <x-ui.data-table :headers="['Ngày', 'Loại', 'Từ', 'Đến', 'Số tiền', 'Tham chiếu', 'Trạng thái', 'Thao tác']">
                @foreach ($documents as $doc)
                    <tr data-testid="treasury-document-row">
                        <td class="text-sm">{{ $doc->transaction_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-sm font-medium">{{ $typeLabels[$doc->document_type] ?? $doc->document_type }}@if (in_array((string) $doc->id, $selfApprovedIds, true)) <span class="ml-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800" data-testid="self-approved-badge">Tự duyệt</span>@endif</td>
                        <td class="text-sm text-slate-600">{{ $endpoint($doc->sourceWallet, $doc->sourceParty) }}</td>
                        <td class="text-sm text-slate-600">{{ $endpoint($doc->destinationWallet, $doc->destinationParty) }}</td>
                        <td class="text-sm font-semibold">{{ $money($doc->amount) }}</td>
                        <td class="text-sm text-slate-600">{{ $doc->reference ?? '—' }}</td>
                        <td class="text-sm">{{ $statusLabels[$doc->status] ?? $doc->status }}</td>
                        <td class="text-sm">
                            @if ($can['reverse'] && in_array($doc->status, ['posted_unreconciled', 'posted_reconciled'], true) && $doc->document_type !== 'reversal')
                                <details>
                                    <summary class="operator-button operator-button-inline">Đảo</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.documents.reverse', [$project->id, $doc->id]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="date" name="transaction_date" class="operator-input" value="{{ now()->toDateString() }}" required>
                                        <input type="text" name="description" class="operator-input" placeholder="Lý do đảo" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận đảo</button>
                                    </form>
                                </details>
                            @elseif ($can['reverse'] && $doc->document_type === 'reversal' && $doc->replacement_document_id === null)
                                <details>
                                    <summary class="operator-button operator-button-inline">Gắn chứng từ thay thế</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.documents.replacement', [$project->id, $doc->id]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <select name="replacement_document_id" class="operator-input" required>
                                            @foreach ($documents->where('document_type', '!=', 'reversal')->where('id', '!=', $doc->reversed_document_id) as $candidate)
                                                <option value="{{ $candidate->id }}">{{ $candidate->transaction_date?->format('d/m/Y') }} — {{ $typeLabels[$candidate->document_type] ?? $candidate->document_type }} — {{ $money($candidate->amount) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="operator-button operator-button-primary">Gắn</button>
                                    </form>
                                </details>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($canManageWallets)
        <x-ui.card title="Thêm ví">
            <form method="POST" action="{{ route('operator.treasury.projects.wallets.store', $project->id) }}" class="space-y-5" data-testid="treasury-wallet-form">
                @csrf
                @include('treasury._wallet-fields', ['wallet' => null])
                <button type="submit" class="operator-button operator-button-primary">Thêm ví</button>
            </form>
        </x-ui.card>
    @endif
@endsection

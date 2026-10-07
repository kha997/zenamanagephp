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
            'adjustment' => 'Điều chỉnh', 'reversal' => 'Bút toán đảo',
        ];
        $statusLabels = [
            'posted_unreconciled' => 'Đã ghi sổ — chưa đối soát', 'posted_reconciled' => 'Đã đối soát', 'reversed' => 'Đã đảo',
        ];
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
        </div>
    </x-ui.card>

    <x-ui.card title="Ví của dự án">
        @if ($wallets->isEmpty())
            <x-ui.empty-state title="Chưa có ví" description="Thêm ví để chuẩn bị ghi nhận tiền của dự án." />
        @else
            <x-ui.data-table :headers="$canManageWallets ? ['Tên ví', 'Loại', 'Người giữ', 'Số dư', 'Thao tác'] : ['Tên ví', 'Loại', 'Người giữ', 'Số dư']">
                @foreach ($wallets as $wallet)
                    <tr data-testid="treasury-wallet-row">
                        <td class="font-medium text-slate-900">{{ $wallet->name }}</td>
                        <td class="text-sm text-slate-600">{{ $walletTypes[$wallet->wallet_type] ?? $wallet->wallet_type }}</td>
                        <td class="text-sm text-slate-600">{{ $wallet->custodianParty?->name ?? '—' }}</td>
                        @php $balance = $summary['wallets'][$wallet->id] ?? '0.00'; @endphp
                        <td class="text-sm font-semibold {{ (float) $balance < 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ $money($balance) }}</td>
                        @if ($canManageWallets)
                            <td class="flex gap-2">
                                <a href="{{ route('operator.treasury.projects.wallets.edit', [$project->id, $wallet->id]) }}" class="operator-button operator-button-inline">Sửa</a>
                                <form method="POST" action="{{ route('operator.treasury.projects.wallets.destroy', [$project->id, $wallet->id]) }}" onsubmit="return confirm('Xoá ví này?')">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-inline">Xoá</button>
                                </form>
                            </td>
                        @endif
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

    <x-ui.card title="Sổ giao dịch">
        @if ($documents->isEmpty())
            <x-ui.empty-state title="Chưa có giao dịch" description="Các khoản tiền nhận, chuyển ví, điều chỉnh sẽ hiện ở đây." />
        @else
            <x-ui.data-table :headers="['Ngày', 'Loại', 'Từ', 'Đến', 'Số tiền', 'Tham chiếu', 'Trạng thái', 'Thao tác']">
                @foreach ($documents as $doc)
                    <tr data-testid="treasury-document-row">
                        <td class="text-sm">{{ $doc->transaction_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-sm font-medium">{{ $typeLabels[$doc->document_type] ?? $doc->document_type }}</td>
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

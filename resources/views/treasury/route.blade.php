@extends('layouts.operator')

@section('title', 'Tuyến tiền — ' . $project->name)
@section('page_title', 'Tuyến tiền')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $typeLabels = ['funding' => 'Tiền nhận', 'owner_contribution' => 'Góp vốn chủ', 'internal_transfer' => 'Chuyển nội bộ'];
        $routeStatusLabels = ['planned' => 'Chưa đi', 'partial' => 'Đang trên đường', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã huỷ'];
        $documentStatusLabels = [
            'approved' => 'Đang trên đường', 'posted_unreconciled' => 'Đã ghi sổ — chưa đối soát',
            'posted_reconciled' => 'Đã đối soát', 'reversed' => 'Đã đảo', 'rejected' => 'Đã huỷ',
        ];
        $open = in_array($route['status'], ['planned', 'partial'], true);
    @endphp

    <x-ui.page-header
        title="Tuyến tiền — {{ $typeLabels[$route['document_type']] ?? $route['document_type'] }} {{ $money($route['total_amount']) }}"
        description="{{ $project->name }}. Ghi một chặng mỗi lần tiền đi qua một chỗ; tuyến tự hoàn tất khi ví đích nhận đủ và các ví trung gian về 0."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')

    <x-ui.card title="Tình trạng">
        <div class="operator-form-grid p-4" data-testid="treasury-route-summary">
            <x-ui.field-value label="Trạng thái tuyến" :value="$routeStatusLabels[$route['status']] ?? $route['status']" />
            <x-ui.field-value label="Chứng từ" :value="$documentStatusLabels[$route['document_status']] ?? $route['document_status']" />
            <x-ui.field-value label="Đã đến ví đích" :value="$money($route['arrived_amount'])" />
            <x-ui.field-value label="Còn lại" :value="$money($route['remaining_amount'])" />
            <x-ui.field-value label="Tham chiếu" :value="$route['reference'] ?? '—'" />
        </div>
    </x-ui.card>

    <x-ui.card title="Tiền của tuyến theo ví">
        <x-ui.data-table :headers="['Ví', 'Đang giữ từ tuyến này']">
            @foreach ($route['net_by_wallet'] as $row)
                <tr data-testid="treasury-route-net-row">
                    <td class="text-sm">{{ $row['wallet_name'] ?? '—' }}</td>
                    <td class="text-sm font-semibold">{{ $money($row['net']) }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </x-ui.card>

    <x-ui.card title="Các chặng">
        @if ($route['legs'] === [])
            <x-ui.empty-state title="Chưa có chặng nào" description="Ghi chặng đầu tiên khi tiền bắt đầu đi." />
        @else
            <x-ui.data-table :headers="['#', 'Ngày', 'Từ', 'Đến', 'Số tiền', 'Trạng thái', 'Thao tác']">
                @foreach ($route['legs'] as $leg)
                    <tr data-testid="treasury-route-leg-row">
                        <td class="text-sm">{{ $leg['sequence_no'] }}</td>
                        <td class="text-sm">{{ $leg['occurred_at'] ? \Illuminate\Support\Carbon::parse($leg['occurred_at'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-sm text-slate-600">{{ $leg['from_wallet_name'] ?? 'Từ bên ngoài' }}</td>
                        <td class="text-sm text-slate-600">{{ $leg['to_wallet_name'] ?? '—' }}</td>
                        <td class="text-sm font-semibold">{{ $money($leg['amount']) }}</td>
                        <td class="text-sm">{{ $leg['status'] === 'reversed' ? 'Đã đảo' : 'Đã ghi' }}</td>
                        <td class="text-sm">
                            @if ($canManageRoutes && $leg['status'] === 'settled' && !in_array($route['document_status'], ['reversed', 'rejected'], true))
                                <details>
                                    <summary class="operator-button operator-button-inline">Đảo chặng</summary>
                                    <form method="POST" action="{{ route('operator.treasury.projects.route-legs.reverse', [$project->id, $leg['id']]) }}" class="mt-2 space-y-2">
                                        @csrf
                                        <input type="text" name="reason" class="operator-input" placeholder="Lý do đảo" maxlength="2000" required>
                                        <button type="submit" class="operator-button operator-button-primary">Xác nhận đảo</button>
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

    @if ($open && $canRecordLeg)
        <x-ui.card title="Ghi chặng">
            <form method="POST" action="{{ route('operator.treasury.projects.routes.legs.store', [$project->id, $route['id']]) }}" class="space-y-5" data-testid="treasury-route-leg-form">
                @csrf
                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="l_from">Từ</label>
                        <select id="l_from" name="from_wallet_id" class="operator-input">
                            @if ($route['document_type'] !== 'internal_transfer')
                                <option value="">Từ bên ngoài (nguồn tiền)</option>
                            @endif
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('from_wallet_id') === $wallet->id)>{{ $wallet->name }} ({{ $money($walletBalances[$wallet->id] ?? 0) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="l_to">Đến ví <span class="text-rose-600">*</span></label>
                        <select id="l_to" name="to_wallet_id" class="operator-input" required>
                            @foreach ($wallets as $wallet)
                                <option value="{{ $wallet->id }}" @selected(old('to_wallet_id', $route['destination_wallet_id']) === $wallet->id)>{{ $wallet->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="operator-field">
                        <label for="l_amount">Số tiền (₫) <span class="text-rose-600">*</span></label>
                        <input id="l_amount" name="amount" type="number" min="0.01" step="0.01" class="operator-input" value="{{ old('amount') }}" required>
                    </div>
                    <div class="operator-field">
                        <label for="l_date">Ngày <span class="text-rose-600">*</span></label>
                        <input id="l_date" name="occurred_at" type="date" class="operator-input" value="{{ old('occurred_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                </div>
                <button type="submit" class="operator-button operator-button-primary">Ghi chặng</button>
            </form>
        </x-ui.card>
    @endif

    @if ($canManageRoutes && $route['document_status'] === 'approved' && $route['status'] !== 'cancelled')
        <x-ui.card title="Huỷ tuyến">
            <form method="POST" action="{{ route('operator.treasury.projects.routes.cancel', [$project->id, $route['id']]) }}" class="space-y-3 p-4" data-testid="treasury-route-cancel-form">
                @csrf
                <p class="text-sm text-slate-600">Chỉ huỷ được khi không còn chặng nào có hiệu lực (đảo các chặng trước). Chứng từ sẽ chuyển "Đã huỷ".</p>
                <input type="text" name="reason" class="operator-input" placeholder="Lý do huỷ" maxlength="2000" required>
                <button type="submit" class="operator-button operator-button-secondary">Huỷ tuyến</button>
            </form>
        </x-ui.card>
    @endif
@endsection

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

    <x-ui.card title="Số dư và giao dịch">
        <p class="p-4 text-sm text-slate-600" data-testid="treasury-ledger-placeholder">
            Số dư, ghi nhận tiền vào, chuyển ví và chi tiêu sẽ có ở bước tiếp theo.
        </p>
    </x-ui.card>

    <x-ui.card title="Ví của dự án">
        @if ($wallets->isEmpty())
            <x-ui.empty-state title="Chưa có ví" description="Thêm ví để chuẩn bị ghi nhận tiền của dự án." />
        @else
            <x-ui.data-table :headers="$canManageWallets ? ['Tên ví', 'Loại', 'Người giữ', 'Thao tác'] : ['Tên ví', 'Loại', 'Người giữ']">
                @foreach ($wallets as $wallet)
                    <tr data-testid="treasury-wallet-row">
                        <td class="font-medium text-slate-900">{{ $wallet->name }}</td>
                        <td class="text-sm text-slate-600">{{ $walletTypes[$wallet->wallet_type] ?? $wallet->wallet_type }}</td>
                        <td class="text-sm text-slate-600">{{ $wallet->custodianParty?->name ?? '—' }}</td>
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

    @if ($canManageWallets)
        <x-ui.card title="Thêm ví">
            @include('treasury._errors')
            <form method="POST" action="{{ route('operator.treasury.projects.wallets.store', $project->id) }}" class="space-y-5" data-testid="treasury-wallet-form">
                @csrf
                @include('treasury._wallet-fields', ['wallet' => null])
                <button type="submit" class="operator-button">Thêm ví</button>
            </form>
        </x-ui.card>
    @endif
@endsection

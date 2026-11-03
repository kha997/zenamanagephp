@extends('layouts.operator')

@section('title', 'Chuỗi quỹ ' . $chain->chain_reference . ' — ' . $project->name)
@section('page_title', 'Chuỗi quỹ')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
        $statusLabels = [
            'posted_unreconciled' => 'Đã ghi sổ — chưa đối soát', 'posted_reconciled' => 'Đã đối soát', 'reversed' => 'Đã đảo',
            'planned' => 'Chưa đi', 'partial' => 'Đang trên đường', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã huỷ',
        ];
    @endphp

    <x-ui.page-header
        title="Chuỗi quỹ {{ $chain->chain_reference }}"
        description="{{ $chain->description ?? $project->name }}"
    >
        <x-ui.button-link :href="route('operator.treasury.projects.fund-chains.index', $project->id)" variant="secondary">Danh sách chuỗi</x-ui.button-link>
        <x-ui.button-link :href="route('operator.treasury.projects.show', ['project' => $project->id, 'fund_chain_id' => $chain->id])" variant="secondary">Xem trong sổ giao dịch</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')
    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif

    <x-ui.card title="Các bước theo thời gian">
        @if ($members === [])
            <p class="p-4 text-sm text-slate-500">Chuỗi chưa có thành viên.</p>
        @else
            <x-ui.data-table :headers="['Ngày', 'Loại', 'Trạng thái', 'Số tiền', 'Tham chiếu', '']">
                @foreach ($members as $member)
                    <tr data-testid="treasury-fund-chain-member">
                        <td class="text-sm">{{ $member['date'] ? \Illuminate\Support\Carbon::parse($member['date'])->format('d/m/Y') : '—' }}</td>
                        <td class="text-sm">
                            @if ($member['kind'] === 'route')
                                <a class="operator-link" href="{{ route('operator.treasury.projects.routes.show', [$project->id, $member['target_id']]) }}">{{ $member['label'] }}</a>
                            @else
                                {{ $member['label'] }}
                            @endif
                        </td>
                        <td class="text-sm text-slate-600">{{ $statusLabels[$member['status']] ?? $member['status'] }}</td>
                        <td class="text-sm">{{ $money($member['amount']) }}</td>
                        <td class="text-sm text-slate-600">{{ $member['reference'] ?? '—' }}</td>
                        <td class="text-sm">
                            @if ($canManage)
                                <form method="POST" action="{{ route('operator.treasury.projects.fund-chains.members.destroy', [$project->id, $chain->id, $member['member_id']]) }}">
                                    @csrf
                                    <button type="submit" class="operator-button operator-button-secondary">Bỏ khỏi chuỗi</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.card title="Thêm vào chuỗi">
            <div class="grid gap-4 p-4 md:grid-cols-2">
                <form method="POST" action="{{ route('operator.treasury.projects.fund-chains.members.store', [$project->id, $chain->id]) }}" class="flex items-end gap-2">
                    @csrf
                    <div class="operator-field flex-1">
                        <label for="member_document">Chứng từ đã ghi sổ</label>
                        <select id="member_document" name="member_financial_document_id" class="operator-input">
                            @foreach ($candidates['documents'] as $candidate)
                                <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary" @disabled($candidates['documents'] === [])>Thêm</button>
                </form>
                <form method="POST" action="{{ route('operator.treasury.projects.fund-chains.members.store', [$project->id, $chain->id]) }}" class="flex items-end gap-2">
                    @csrf
                    <div class="operator-field flex-1">
                        <label for="member_route">Tuyến tiền</label>
                        <select id="member_route" name="member_payment_route_id" class="operator-input">
                            @foreach ($candidates['routes'] as $candidate)
                                <option value="{{ $candidate['id'] }}">{{ $candidate['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="operator-button operator-button-primary" @disabled($candidates['routes'] === [])>Thêm</button>
                </form>
            </div>
        </x-ui.card>
    @endif
@endsection

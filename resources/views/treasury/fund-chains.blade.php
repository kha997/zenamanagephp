@extends('layouts.operator')

@section('title', 'Chuỗi quỹ — ' . $project->name)
@section('page_title', 'Chuỗi quỹ')

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫';
    @endphp

    <x-ui.page-header
        title="Chuỗi quỹ — {{ $project->name }}"
        description="Gom các khoản tiền nhận, chuyển và chi để truy vết dòng tiền. Gắn chuỗi chỉ là nhãn, không đổi số dư."
    >
        <x-ui.button-link :href="route('operator.treasury.projects.show', $project->id)" variant="secondary">Quay lại ngân quỹ</x-ui.button-link>
    </x-ui.page-header>

    @include('treasury._errors')
    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif

    <x-ui.card title="Danh sách chuỗi">
        @if ($chains === [])
            <p class="p-4 text-sm text-slate-500" data-testid="treasury-fund-chains-empty">Chưa có chuỗi quỹ.</p>
        @else
            <x-ui.data-table :headers="['Mã chuỗi', 'Mô tả', 'Số thành viên', 'Tiền vào', 'Tiền ra (chi / tạm ứng)']">
                @foreach ($chains as $chain)
                    <tr data-testid="treasury-fund-chain-row">
                        <td class="text-sm font-medium"><a class="operator-link" href="{{ route('operator.treasury.projects.fund-chains.show', [$project->id, $chain['id']]) }}">{{ $chain['chain_reference'] }}</a></td>
                        <td class="text-sm text-slate-600">{{ $chain['description'] ?? '—' }}</td>
                        <td class="text-sm">{{ $chain['member_count'] }}</td>
                        <td class="text-sm">{{ $money($chain['money_in']) }}</td>
                        <td class="text-sm">{{ $money($chain['money_out']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>

    @if ($canManage)
        <x-ui.card title="Tạo chuỗi quỹ">
            <form method="POST" action="{{ route('operator.treasury.projects.fund-chains.store', $project->id) }}" class="flex flex-wrap items-end gap-2 p-4" data-testid="treasury-fund-chain-form">
                @csrf
                <div class="operator-field w-48">
                    <label for="chain_reference">Mã chuỗi</label>
                    <input id="chain_reference" name="chain_reference" type="text" class="operator-input" maxlength="100" required value="{{ old('chain_reference') }}">
                </div>
                <div class="operator-field min-w-64 flex-1">
                    <label for="chain_description">Mô tả</label>
                    <input id="chain_description" name="description" type="text" class="operator-input" maxlength="2000" value="{{ old('description') }}">
                </div>
                <button type="submit" class="operator-button operator-button-primary">Tạo chuỗi</button>
            </form>
        </x-ui.card>
    @endif
@endsection

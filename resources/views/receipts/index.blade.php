@extends('layouts.operator')

@section('title', 'Phiếu nhập')
@section('page_title', 'Phiếu nhập')

@section('content')
    <x-ui.page-header
        title="Phiếu nhập"
        description="Danh sách phiếu nhập và liên kết hợp đồng/yêu cầu vật tư."
    >
        <x-ui.button-link :href="route('operator.receipts.create')">Tạo phiếu nhập</x-ui.button-link>
    </x-ui.page-header>

    @if ($receipts->isEmpty())
        <x-ui.empty-state
            title="Chưa có phiếu nhập"
            description="Tạo phiếu nhập đầu tiên để ghi nhận vật tư nhận theo dự án."
        >
            <x-ui.button-link :href="route('operator.receipts.create')">Tạo phiếu nhập</x-ui.button-link>
        </x-ui.empty-state>
    @else
        <x-ui.card>
            <x-ui.data-table :headers="['Mã phiếu nhập', 'Dự án', 'Hợp đồng', 'Yêu cầu vật tư', 'Ngày nhập', 'Thao tác']">
                @foreach ($receipts as $receipt)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $receipt->receipt_number }}</td>
                        <td>{{ $receipt->project?->name ?? '—' }}</td>
                        <td>{{ $receipt->contract?->code ?? '—' }}</td>
                        <td>{{ $receipt->materialRequest?->request_number ?? '—' }}</td>
                        <td class="text-sm text-slate-600">{{ optional($receipt->receipt_date)->format('d/m/Y') }}</td>
                        <td>
                            <x-ui.button-link :href="route('operator.receipts.show', $receipt->id)" variant="inline">Xem</x-ui.button-link>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        </x-ui.card>
    @endif
@endsection

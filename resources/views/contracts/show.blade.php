@extends('layouts.operator')

@section('title', 'Hợp đồng ' . $contract->code)
@section('page_title', 'Hợp đồng ' . $contract->code)

@section('content')
    <x-ui.page-header
        :title="'Hợp đồng ' . $contract->code"
        :description="$contract->title"
    >
        <x-ui.button-link :href="route('operator.contracts.pdf', $contract->id)" variant="secondary">Tải PDF</x-ui.button-link>
        <x-ui.button-link :href="route('operator.contracts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if ($hasQuoteDrift)
        <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
            Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp.
        </p>
    @endif

    <div class="space-y-6">
        <x-ui.card title="Thông tin hợp đồng">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field-value label="Mã" :value="$contract->code" />
                <x-ui.field-value label="Dự án" :value="($contract->project?->name ?? '—') . ($contract->project?->code ? ' (' . $contract->project->code . ')' : '')" />
                <x-ui.field-value label="Trạng thái" :value="match($contract->status) { 'active' => 'Đang hiệu lực', 'draft' => 'Nháp', 'closed' => 'Đã đóng', 'cancelled' => 'Đã hủy', default => $contract->status }" />
                <x-ui.field-value label="Giá trị" :value="$contract->total_value !== null ? number_format((float) $contract->total_value) . ' ' . $contract->currency : null" />
                <x-ui.field-value label="Ngày ký" :value="optional($contract->signed_at)->format('d/m/Y')" />
                <x-ui.field-value label="Hiệu lực" :value="(optional($contract->start_date)->format('d/m/Y') ?? '—') . ' → ' . (optional($contract->end_date)->format('d/m/Y') ?? '—')" />
            </div>
        </x-ui.card>

        <x-ui.card title="Tổng hợp chi phí">
            @if ($summary)
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.field-value label="Giá trị hợp đồng" :value="number_format((float) data_get($summary, 'contract_total_value', 0))" />
                    <x-ui.field-value label="Đã nhập kho" :value="number_format((float) data_get($summary, 'receipt_total', data_get($summary, 'received_total', 0)))" />
                    <x-ui.field-value label="Đã thanh toán" :value="number_format((float) data_get($summary, 'paid_total', data_get($summary, 'payment_total', 0)))" />
                    <x-ui.field-value label="Còn lại" :value="number_format((float) data_get($summary, 'remaining', data_get($summary, 'remaining_value', 0)))" />
                </div>
            @else
                <div class="py-4 text-sm text-slate-500">{{ $summaryUnavailableMessage ?? 'Chưa có dữ liệu chi phí.' }}</div>
            @endif
        </x-ui.card>
    </div>
@endsection

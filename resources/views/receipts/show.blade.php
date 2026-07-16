@extends('layouts.operator')

@section('title', 'Chi tiết phiếu nhập')
@section('page_title', 'Chi tiết phiếu nhập')

@section('content')
    <x-ui.page-header
        :title="$receipt->receipt_number"
        description="Chi tiết phiếu nhập, dòng vật tư và tổng hợp chi phí hợp đồng."
    >
        <x-ui.button-link :href="route('operator.receipts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if ($errors->any())
        <div class="operator-error-list">
            <ul class="space-y-1 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">
        <x-ui.card title="Thông tin phiếu nhập">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.field-value label="Mã phiếu nhập" :value="$receipt->receipt_number" />
                <x-ui.field-value label="Dự án" :value="$receipt->project?->name" />
                <x-ui.field-value label="Hợp đồng" :value="$receipt->contract?->code" />
                <x-ui.field-value label="Yêu cầu vật tư" :value="$receipt->materialRequest?->request_number" />
                <x-ui.field-value label="Nhà cung cấp" :value="$receipt->vendor?->name" />
                <x-ui.field-value label="Ngày nhập" :value="optional($receipt->receipt_date)->format('d/m/Y')" />
            </div>
        </x-ui.card>

        <x-ui.card title="Dòng vật tư" description="Thêm dòng vật tư trực tiếp trên phiếu nhập này.">
            @if ($receipt->lines->isEmpty())
                <div class="mb-6">
                    <x-ui.empty-state
                        title="Chưa có dòng vật tư"
                        description="Thêm dòng đầu tiên để ghi nhận số lượng và đơn giá nhận thực tế."
                    />
                </div>
            @else
                <div class="mb-6">
                    <x-ui.data-table :headers="['Vật tư', 'Số lượng', 'Đơn giá', 'Thành tiền']">
                        @foreach ($receipt->lines as $line)
                            <tr>
                                <td>
                                    <div class="font-semibold text-slate-900">{{ $line->material?->name ?? '—' }}</div>
                                    <div class="text-sm text-slate-500">{{ $line->material?->code ?? '' }}</div>
                                </td>
                                <td>{{ number_format((float) $line->quantity_received, 3) }}</td>
                                <td>
                                    @if ($line->unit_cost !== null)
                                        {{ number_format((float) $line->unit_cost, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="font-medium text-slate-900">
                                    @if ($line->unit_cost !== null)
                                        {{ number_format((float) $line->quantity_received * (float) $line->unit_cost, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-ui.data-table>
                </div>
            @endif

            <form method="POST" action="{{ route('operator.receipts.lines.store', $receipt->id) }}" class="space-y-5 border-t border-slate-200 pt-6">
                @csrf

                <div class="operator-form-grid">
                    <div class="operator-field">
                        <label for="material_id">Vật tư</label>
                        <select id="material_id" name="material_id" class="operator-select" required>
                            <option value="">Chọn vật tư</option>
                            @foreach ($materials as $material)
                                <option value="{{ $material->id }}" @selected(old('material_id') === (string) $material->id)>
                                    {{ $material->name }}{{ $material->code ? ' (' . $material->code . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="operator-field">
                        <label for="quantity_received">Số lượng nhận</label>
                        <input id="quantity_received" name="quantity_received" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('quantity_received') }}" required>
                    </div>

                    <div class="operator-field">
                        <label for="unit_cost">Đơn giá</label>
                        <input id="unit_cost" name="unit_cost" type="text" inputmode="decimal" data-money class="operator-input" value="{{ old('unit_cost') }}" placeholder="vd: 185.000">
                    </div>
                </div>

                <button type="submit" class="operator-button operator-button-primary">Thêm dòng vật tư</button>
            </form>
        </x-ui.card>

        <x-ui.card title="Tổng hợp chi phí hợp đồng" description="Đọc trực tiếp từ tổng hợp chi phí hợp đồng hiện tại.">
            @if (!$receipt->contract_id)
                <div class="mb-6">
                    <x-ui.empty-state
                        title="Chưa có dữ liệu tổng hợp chi phí"
                        description="Phiếu nhập này chưa gắn hợp đồng nên chưa thể hiển thị tổng hợp chi phí."
                    />
                </div>
            @elseif (!$summary)
                <div class="mb-6">
                    <x-ui.empty-state
                        title="Chưa tải được tổng hợp chi phí"
                        :description="$summaryUnavailableMessage ?? 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.'"
                    />
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-5">
                    <x-ui.field-value label="Tổng chi phí" :value="$summary ? number_format((float) $summary['priced_line_cost_total'], 2) : '—'" />
                    <x-ui.field-value label="Tổng số dòng" :value="$summary['line_count'] ?? '—'" />
                    <x-ui.field-value label="Số dòng có giá" :value="$summary['priced_line_count'] ?? '—'" />
                    <x-ui.field-value label="Số dòng chưa có giá" :value="$summary['unpriced_line_count'] ?? '—'" />
                    <x-ui.field-value label="Số phiếu nhập đã gắn" :value="$summary['mapped_receipt_count'] ?? '—'" />
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection

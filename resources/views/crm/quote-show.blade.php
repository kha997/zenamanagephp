@extends('layouts.operator')

@section('title', $quote->quote_number . ' — Báo giá')
@section('page_title', 'Chi tiết báo giá')

@php
    $statusLabels = [
        'draft' => 'Nháp',
        'sent' => 'Đã gửi',
        'accepted' => 'Đã chấp nhận',
        'rejected' => 'Đã từ chối',
        'superseded' => 'Đã thay thế',
    ];
    $amountInWords = \App\Support\VietnameseMoneyWords::toWords((float) $quote->subtotal);
@endphp

@section('content')
    <x-ui.page-header
        title="{{ $quote->quote_number }} — Rev {{ $quote->revision_no }}"
        description="Cơ hội: {{ $quote->opportunity?->opportunity_name ?? '—' }}"
    >
        <x-ui.button-link :href="route('operator.crm.opportunities.show', $quote->opportunity_id)" variant="secondary">Quay lại cơ hội</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.toast />

    {{-- Quote Info --}}
    <x-ui.card title="Thông tin báo giá">
        <div class="operator-form-grid">
            <x-ui.field-value label="Số báo giá" :value="$quote->quote_number" />
            <x-ui.field-value label="Revision" :value="$quote->revision_no" />
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$quote->status" />
            </x-ui.field-value>
            <x-ui.field-value label="Tổng cộng" :value="number_format($quote->subtotal, 0, ',', '.') . '₫'" />
            <x-ui.field-value label="Bằng chữ" :value="$amountInWords" />
            @if ($quote->valid_until)
                <x-ui.field-value label="Hiệu lực đến" :value="$quote->valid_until->format('d/m/Y')" />
            @endif
            @if ($quote->sent_at)
                <x-ui.field-value label="Ngày gửi" :value="$quote->sent_at->format('d/m/Y H:i')" />
            @endif
            @if ($quote->decided_at)
                <x-ui.field-value label="Ngày chốt" :value="$quote->decided_at->format('d/m/Y H:i')" />
            @endif
        </div>
        @if ($quote->notes)
            <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $quote->notes }}</p>
        @endif
    </x-ui.card>

    {{-- Line Items --}}
    <x-ui.card title="Dòng báo giá">
        @php
            $lines = $quote->lines()->get();
        @endphp

        @if ($lines->isEmpty())
            <p class="text-sm text-slate-500">Chưa có dòng.</p>
        @else
            <div class="overflow-x-auto">
                <table class="operator-table text-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Mã</th>
                            <th>Hạng mục</th>
                            <th>ĐVT</th>
                            <th>KL</th>
                            <th>Đơn giá</th>
                            <th>Thành tiền</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td>{{ $line->sort_order }}</td>
                                <td>{{ $line->code ?? '—' }}</td>
                                <td class="font-medium">{{ $line->name }}</td>
                                <td>{{ $line->unit }}</td>
                                <td class="text-right">{{ number_format($line->quantity, 3, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($line->unit_price, 0, ',', '.') }}₫</td>
                                <td class="text-right">{{ number_format($line->amount, 0, ',', '.') }}₫</td>
                                <td class="text-sm text-slate-500">{{ $line->price_note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-bold">
                            <td colspan="6" class="text-right">Tổng cộng</td>
                            <td class="text-right">{{ number_format($quote->subtotal, 0, ',', '.') }}₫</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </x-ui.card>

    {{-- Actions --}}
    @if (auth()->user()?->hasPermission('crm.manage'))
        @if ($quote->status === 'draft')
            {{-- Save Lines Form --}}
            <x-ui.card title="Chỉnh sửa dòng">
                <form method="POST" action="{{ route('operator.crm.quotes.lines.save', $quote->id) }}">
                    @csrf
                    <div id="lines-container">
                        @foreach ($lines as $i => $line)
                            <div class="flex flex-wrap items-end gap-2 mb-2 line-row" data-index="{{ $i }}">
                                <input type="hidden" name="lines[{{ $i }}][sort_order]" value="{{ $i + 1 }}">
                                <div class="operator-field flex-1 min-w-32">
                                    <label>Tên</label>
                                    <input type="text" name="lines[{{ $i }}][name]" value="{{ $line->name }}" class="operator-input" required>
                                </div>
                                <div class="operator-field w-20">
                                    <label>ĐVT</label>
                                    <input type="text" name="lines[{{ $i }}][unit]" value="{{ $line->unit }}" class="operator-input" required>
                                </div>
                                <div class="operator-field w-24">
                                    <label>KL</label>
                                    <input type="number" name="lines[{{ $i }}][quantity]" value="{{ $line->quantity }}" class="operator-input" step="0.001" min="0.001" required>
                                </div>
                                <div class="operator-field w-32">
                                    <label>Đơn giá</label>
                                    <input type="number" name="lines[{{ $i }}][unit_price]" value="{{ $line->unit_price }}" class="operator-input" step="0.01" min="0" required>
                                </div>
                                <div class="operator-field flex-1 min-w-32">
                                    <label>Ghi chú đơn giá</label>
                                    <input type="text" name="lines[{{ $i }}][price_note]" value="{{ $line->price_note }}" class="operator-input">
                                </div>
                                <input type="hidden" name="lines[{{ $i }}][code]" value="{{ $line->code }}">
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button type="submit" class="operator-button operator-button-primary">Lưu dòng</button>
                        <button type="button" onclick="addLine()" class="operator-button">+ Thêm dòng</button>
                    </div>
                </form>

                {{-- Send Button --}}
                <form method="POST" action="{{ route('operator.crm.quotes.send', $quote->id) }}" class="mt-3" onsubmit="return confirm('Gửi báo giá cho khách hàng?')">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Gửi khách</button>
                </form>
            </x-ui.card>
        @elseif ($quote->status === 'sent')
            {{-- Accept / Reject / Revise --}}
            <x-ui.card title="Hành động">
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('operator.crm.quotes.accept', $quote->id) }}" onsubmit="return confirm('Xác nhận khách đã chấp nhận báo giá này?')">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Khách chấp nhận</button>
                    </form>
                    <form method="POST" action="{{ route('operator.crm.quotes.reject', $quote->id) }}" onsubmit="return confirm('Xác nhận khách từ chối?')">
                        @csrf
                        <button type="submit" class="operator-button">Khách từ chối</button>
                    </form>
                    <form method="POST" action="{{ route('operator.crm.quotes.revise', $quote->id) }}">
                        @csrf
                        <button type="submit" class="operator-button">Tạo bản chào mới</button>
                    </form>
                </div>
            </x-ui.card>
        @endif
    @endif

    {{-- PDF link will be added in Task 4 --}}
@endsection

@push('scripts')
<script>
    function addLine() {
        const container = document.getElementById('lines-container');
        const index = container.querySelectorAll('.line-row').length;
        const row = document.createElement('div');
        row.className = 'flex flex-wrap items-end gap-2 mb-2 line-row';
        row.dataset.index = index;
        row.innerHTML = `
            <input type="hidden" name="lines[${index}][sort_order]" value="${index + 1}">
            <div class="operator-field flex-1 min-w-32">
                <label>Tên</label>
                <input type="text" name="lines[${index}][name]" class="operator-input" required>
            </div>
            <div class="operator-field w-20">
                <label>ĐVT</label>
                <input type="text" name="lines[${index}][unit]" class="operator-input" required>
            </div>
            <div class="operator-field w-24">
                <label>KL</label>
                <input type="number" name="lines[${index}][quantity]" class="operator-input" step="0.001" min="0.001" required>
            </div>
            <div class="operator-field w-32">
                <label>Đơn giá</label>
                <input type="number" name="lines[${index}][unit_price]" class="operator-input" step="0.01" min="0" required>
            </div>
            <div class="operator-field flex-1 min-w-32">
                <label>Ghi chú đơn giá</label>
                <input type="text" name="lines[${index}][price_note]" class="operator-input">
            </div>
            <input type="hidden" name="lines[${index}][code]" value="">
        `;
        container.appendChild(row);
    }
</script>
@endpush

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
    $amountInWords = \App\Support\VietnameseMoneyWords::toWords((float) $quote->total);
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
            <x-ui.field-value label="Tạm tính" :value="number_format($quote->subtotal, 0, ',', '.') . '₫'" />
            @if ((float) ($quote->discount_amount ?? 0) > 0)
                <x-ui.field-value label="Chiết khấu ({{ number_format($quote->discount_percent, 2, ',', '.') }}%)" :value="'−' . number_format($quote->discount_amount, 0, ',', '.') . '₫'" />
            @endif
            @if ((float) ($quote->vat_amount ?? 0) > 0)
                <x-ui.field-value label="VAT ({{ number_format($quote->vat_percent, 2, ',', '.') }}%)" :value="'+' . number_format($quote->vat_amount, 0, ',', '.') . '₫'" />
            @endif
            <x-ui.field-value label="Tổng cộng" :value="number_format($quote->total, 0, ',', '.') . '₫'" />
            <x-ui.field-value label="Bằng chữ" :value="$amountInWords" />
            @if ($quote->payment_terms)
                <x-ui.field-value label="Điều khoản thanh toán" :value="$quote->payment_terms" />
            @endif
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
                            <td colspan="6" class="text-right">Tạm tính</td>
                            <td class="text-right">{{ number_format($quote->subtotal, 0, ',', '.') }}₫</td>
                            <td></td>
                        </tr>
                        @if ((float) ($quote->discount_amount ?? 0) > 0)
                            <tr class="text-red-600">
                                <td colspan="6" class="text-right">Chiết khấu ({{ number_format($quote->discount_percent, 2, ',', '.') }}%)</td>
                                <td class="text-right">−{{ number_format($quote->discount_amount, 0, ',', '.') }}₫</td>
                                <td></td>
                            </tr>
                        @endif
                        @if ((float) ($quote->vat_amount ?? 0) > 0)
                            <tr class="text-blue-600">
                                <td colspan="6" class="text-right">VAT ({{ number_format($quote->vat_percent, 2, ',', '.') }}%)</td>
                                <td class="text-right">+{{ number_format($quote->vat_amount, 0, ',', '.') }}₫</td>
                                <td></td>
                            </tr>
                        @endif
                        <tr class="font-bold text-lg border-t-2">
                            <td colspan="6" class="text-right">Tổng cộng</td>
                            <td class="text-right">{{ number_format($quote->total, 0, ',', '.') }}₫</td>
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
                                <div class="operator-field w-24">
                                    <label>Mã công tác</label>
                                    <input type="text" name="lines[{{ $i }}][code]" value="{{ $line->code }}" class="operator-input line-code-input" data-index="{{ $i }}" oninput="lookupPriceReference({{ $i }})">
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
                                <div class="operator-field w-40">
                                    <label>Nguồn giá (tuỳ chọn)</label>
                                    <select name="lines[{{ $i }}][benchmark_type]" class="operator-input">
                                        <option value="">— Không lưu chứng cứ —</option>
                                        @foreach (\App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="operator-field flex-1 min-w-32">
                                    <label>Ghi chú chứng cứ</label>
                                    <input type="text" name="lines[{{ $i }}][evidence_note]" class="operator-input">
                                </div>
                                <div class="operator-field w-32">
                                    <label>Ngày chứng cứ</label>
                                    <input type="date" name="lines[{{ $i }}][evidence_date]" class="operator-input" max="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="w-full text-sm text-gray-500 price-reference-hint" id="price-reference-hint-{{ $i }}"></div>
                                <button type="button" class="operator-button text-xs" onclick="showPriceReferenceHistory({{ $i }})">Xem lịch sử giá</button>
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

            {{-- Commercial Form --}}
            <x-ui.card title="Thông tin thương mại">
                <form method="POST" action="{{ route('operator.crm.quotes.commercial', $quote->id) }}">
                    @csrf
                    <div class="operator-form-grid">
                        <div class="operator-field">
                            <label for="discount_percent">Chiết khấu (%)</label>
                            <input type="number" id="discount_percent" name="discount_percent" value="{{ $quote->discount_percent }}" class="operator-input" step="0.01" min="0" max="100">
                        </div>
                        <div class="operator-field">
                            <label for="vat_percent">VAT (%)</label>
                            <input type="number" id="vat_percent" name="vat_percent" value="{{ $quote->vat_percent }}" class="operator-input" step="0.01" min="0" max="100">
                        </div>
                        <div class="operator-field">
                            <label for="valid_until">Hiệu lực đến</label>
                            <input type="date" id="valid_until" name="valid_until" value="{{ $quote->valid_until?->format('Y-m-d') }}" class="operator-input">
                        </div>
                        <div class="operator-field">
                            <label for="payment_terms">Điều khoản thanh toán</label>
                            <input type="text" id="payment_terms" name="payment_terms" value="{{ $quote->payment_terms }}" class="operator-input" placeholder="VD: Net 30">
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="operator-button operator-button-primary">Lưu thông tin thương mại</button>
                    </div>
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

    {{-- Document Templates --}}
    @if (!empty($quoteTemplates) && $quoteTemplates->isNotEmpty())
        <x-ui.card title="Xuất theo biểu mẫu">
            <div class="flex flex-wrap gap-2">
                @foreach ($quoteTemplates as $tpl)
                    <a href="{{ route('operator.crm.quotes.render-document', [$quote->id, $tpl->id]) }}" class="operator-button">{{ $tpl->name }}</a>
                @endforeach
            </div>
        </x-ui.card>
    @endif
@endsection

@push('scripts')
<script>
    window.zenaBenchmarkTypeOptions = `@foreach (\App\Models\PriceReferenceEntry::BENCHMARK_TYPE_LABELS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach`;

    let lookupDebounceTimers = {};

    function lookupPriceReference(index) {
        clearTimeout(lookupDebounceTimers[index]);
        lookupDebounceTimers[index] = setTimeout(() => {
            const row = document.querySelector(`.line-row[data-index="${index}"]`) || document.querySelector(`.line-code-input[data-index="${index}"]`).closest('.line-row');
            const codeInput = row.querySelector(`[name="lines[${index}][code]"]`);
            const unitInput = row.querySelector(`[name="lines[${index}][unit]"]`);
            const priceInput = row.querySelector(`[name="lines[${index}][unit_price]"]`);
            const hint = document.getElementById(`price-reference-hint-${index}`);

            const code = codeInput.value.trim();
            const unit = unitInput.value.trim();
            if (!code || !unit) {
                hint.textContent = '';
                return;
            }

            fetch(`{{ route('operator.crm.price-references.lookup') }}?code=${encodeURIComponent(code)}&unit=${encodeURIComponent(unit)}`)
                .then(r => r.json())
                .then(({ data }) => {
                    if (!data) {
                        hint.textContent = '';
                        return;
                    }
                    if (!priceInput.value) {
                        priceInput.value = data.unit_price;
                    }
                    hint.textContent = `Tham chiếu: ${data.unit_price.toLocaleString('vi-VN')}đ — ${data.benchmark_type_label}, ${data.evidenced_at}`;
                });
        }, 400);
    }

    function showPriceReferenceHistory(index) {
        const row = document.querySelector(`.line-code-input[data-index="${index}"]`).closest('.line-row');
        const code = row.querySelector(`[name="lines[${index}][code]"]`).value.trim();
        const unit = row.querySelector(`[name="lines[${index}][unit]"]`).value.trim();
        if (!code || !unit) {
            alert('Cần nhập mã công tác và đơn vị tính trước.');
            return;
        }

        fetch(`{{ route('operator.crm.price-references.history') }}?code=${encodeURIComponent(code)}&unit=${encodeURIComponent(unit)}`)
            .then(r => r.json())
            .then(({ data }) => {
                if (!data.length) {
                    alert('Chưa có lịch sử giá cho công tác này.');
                    return;
                }
                const lines = data.map(e => `${e.evidenced_at} — ${e.unit_price.toLocaleString('vi-VN')}đ — ${e.benchmark_type_label}${e.evidence_note ? ' — ' + e.evidence_note : ''}`);
                alert('Lịch sử giá:\n\n' + lines.join('\n'));
            });
    }

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
            <div class="operator-field w-24">
                <label>Mã công tác</label>
                <input type="text" name="lines[${index}][code]" class="operator-input line-code-input" data-index="${index}" oninput="lookupPriceReference(${index})">
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
            <div class="operator-field w-40">
                <label>Nguồn giá (tuỳ chọn)</label>
                <select name="lines[${index}][benchmark_type]" class="operator-input">
                    <option value="">— Không lưu chứng cứ —</option>
                    ${window.zenaBenchmarkTypeOptions}
                </select>
            </div>
            <div class="operator-field flex-1 min-w-32">
                <label>Ghi chú chứng cứ</label>
                <input type="text" name="lines[${index}][evidence_note]" class="operator-input">
            </div>
            <div class="operator-field w-32">
                <label>Ngày chứng cứ</label>
                <input type="date" name="lines[${index}][evidence_date]" class="operator-input">
            </div>
            <div class="w-full text-sm text-gray-500 price-reference-hint" id="price-reference-hint-${index}"></div>
            <button type="button" class="operator-button text-xs" onclick="showPriceReferenceHistory(${index})">Xem lịch sử giá</button>
        `;
        container.appendChild(row);
    }
</script>
@endpush

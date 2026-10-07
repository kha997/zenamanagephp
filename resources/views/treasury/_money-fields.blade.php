<div class="operator-field">
    <label for="{{ $prefix }}_amount">Số tiền (₫) <span class="text-rose-600">*</span></label>
    <input id="{{ $prefix }}_amount" name="amount" type="number" min="0.01" step="0.01" class="operator-input" value="{{ old('amount') }}" required>
</div>
<div class="operator-field">
    <label for="{{ $prefix }}_date">Ngày giao dịch <span class="text-rose-600">*</span></label>
    <input id="{{ $prefix }}_date" name="transaction_date" type="date" class="operator-input" value="{{ old('transaction_date', now()->toDateString()) }}" required>
</div>
<div class="operator-field">
    <label for="{{ $prefix }}_reference">Số tham chiếu</label>
    <input id="{{ $prefix }}_reference" name="reference" type="text" class="operator-input" maxlength="100" value="{{ old('reference') }}" placeholder="Số UNC, phiếu thu…">
</div>
<div class="operator-field">
    <label for="{{ $prefix }}_description">{{ ($reasonRequired ?? false) ? 'Lý do' : 'Ghi chú' }} @if ($reasonRequired ?? false)<span class="text-rose-600">*</span>@endif</label>
    <input id="{{ $prefix }}_description" name="description" type="text" class="operator-input" maxlength="2000" value="{{ old('description') }}" @if ($reasonRequired ?? false) required @endif>
</div>

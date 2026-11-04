@extends('layouts.operator')

@section('title', 'Bảng lương ' . $period->period)
@section('page_title', 'Bảng lương ' . $period->period)

@section('content')
    {{-- GAP-109 C-1 / B-1 / H-1 / L-1 / P-1: one month's payroll. --}}
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.');
        $closed = $period->isClosed();
        $labels = [
            'base_salary' => 'Lương cơ bản', 'allowance' => 'Phụ cấp', 'overtime' => 'Tăng ca', 'bonus_paid' => 'Thưởng thực trả',
            'employer_insurance' => 'BH công ty đóng', 'other_company_cost' => 'Chi phí khác', 'bonus_accrual' => 'Thưởng phân bổ (1/12)',
            'employee_insurance' => 'BH người lao động', 'personal_income_tax' => 'Thuế TNCN', 'advance_deduction' => 'Trừ tạm ứng', 'other_deduction' => 'Khấu trừ khác',
        ];
    @endphp
    <x-ui.page-header :title="'Bảng lương tháng ' . $period->period" :description="($closed ? 'Đã chốt' : 'Nháp') . ' — ' . $period->working_days . ' ngày công × ' . (float) $period->hours_per_day . ' giờ.'">
        <x-ui.button-link :href="route('operator.payroll.index')" variant="secondary">Tất cả tháng</x-ui.button-link>
        <x-ui.button-link :href="route('operator.payroll.periods.export', $period->id)" variant="secondary">Xuất CSV</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700" data-testid="payroll-errors">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <x-ui.card title="Tổng tháng">
        <div class="operator-form-grid p-4" data-testid="payroll-totals">
            <x-ui.field-value label="Thực nhận" :value="$money($totals['net_pay']) . ' ₫'" />
            <x-ui.field-value label="Tổng chi phí công ty" :value="$money($totals['company_cost']) . ' ₫'" />
            <x-ui.field-value label="BH công ty đóng" :value="$money($totals['employer_insurance']) . ' ₫'" />
            <x-ui.field-value label="Thưởng phân bổ" :value="$money($totals['bonus_accrual']) . ' ₫'" />
        </div>
        <div class="flex flex-wrap gap-3 px-4 pb-4">
            @if (!$closed)
                <form method="POST" action="{{ route('operator.payroll.periods.settings', $period->id) }}" class="flex flex-wrap items-end gap-2" data-testid="payroll-settings-form">
                    @csrf
                    <label class="text-sm">Ngày công <input type="number" name="working_days" min="1" max="31" class="operator-input mt-1 w-20" value="{{ $period->working_days }}"></label>
                    <label class="text-sm">Giờ / ngày <input type="number" name="hours_per_day" min="1" max="24" step="0.5" class="operator-input mt-1 w-20" value="{{ (float) $period->hours_per_day }}"></label>
                    <button type="submit" class="operator-button operator-button-secondary">Lưu</button>
                </form>
            @endif
            @if ($canClose)
                @if (!$closed)
                    <form method="POST" action="{{ route('operator.payroll.periods.close', $period->id) }}" data-testid="payroll-close-form" onsubmit="return confirm('Chốt bảng lương tháng này?')">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Chốt bảng lương</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('operator.payroll.periods.reopen', $period->id) }}" class="flex items-end gap-2" data-testid="payroll-reopen-form">
                        @csrf
                        <label class="text-sm">Lý do mở lại <input type="text" name="reason" maxlength="500" required class="operator-input mt-1 w-64"></label>
                        <button type="submit" class="operator-button operator-button-secondary">Mở lại</button>
                    </form>
                @endif
            @endif
        </div>
    </x-ui.card>

    <x-ui.card title="Từng người">
        @if ($lines->isEmpty())
            <x-ui.empty-state title="Không có ai trong tháng" description="Bảng lương lấy những người đang làm trong tháng theo ngày bắt đầu / ngày nghỉ." />
        @else
            <x-ui.data-table :headers="['Nhân sự', 'Thực nhận', 'Tổng chi phí công ty', 'Giờ chuẩn', 'Đơn giá giờ', 'Đã trả', '']">
                @foreach ($lines as $line)
                    <tr data-testid="payroll-line-row">
                        <td class="text-sm"><span class="font-semibold">{{ $line->staffMember->code }}</span> {{ $line->staffMember->name }}@if ($line->staffMember->is_overhead) <span class="text-xs text-slate-500">(văn phòng)</span>@endif</td>
                        <td class="text-sm">{{ $money($line->net_pay) }}</td>
                        <td class="text-sm font-semibold">{{ $money($line->company_cost) }}</td>
                        <td class="text-sm">{{ (float) $line->standard_hours }}</td>
                        <td class="text-sm">{{ $money($line->hourly_cost) }}</td>
                        <td class="text-sm">{{ $line->paid_at ? $line->paid_at->format('d/m/Y') . ($line->payment_reference ? ' — ' . $line->payment_reference : '') : '—' }}</td>
                        <td class="text-sm">
                            <details>
                                <summary class="operator-button operator-button-inline">{{ $closed ? 'Chi tiết' : 'Nhập' }}</summary>
                                @if (!$closed)
                                    <form method="POST" action="{{ route('operator.payroll.lines.update', $line->id) }}" class="mt-2 grid grid-cols-2 gap-2" data-testid="payroll-line-form">
                                        @csrf
                                        @foreach ($labels as $column => $label)
                                            <label class="text-xs">{{ $label }}<input type="number" min="0" step="1" name="{{ $column }}" class="operator-input mt-1" value="{{ (float) $line->{$column} }}"></label>
                                        @endforeach
                                        <label class="col-span-2 text-xs">Ghi chú<input type="text" name="note" maxlength="500" class="operator-input mt-1" value="{{ $line->note }}"></label>
                                        <button type="submit" class="operator-button operator-button-primary col-span-2">Lưu</button>
                                    </form>
                                @else
                                    <dl class="mt-2 grid grid-cols-2 gap-1 text-xs">
                                        @foreach ($labels as $column => $label)<dt>{{ $label }}</dt><dd>{{ $money($line->{$column}) }}</dd>@endforeach
                                    </dl>
                                    <form method="POST" action="{{ route('operator.payroll.lines.paid', $line->id) }}" class="mt-2 flex flex-wrap items-end gap-2" data-testid="payroll-paid-form">
                                        @csrf
                                        <label class="text-xs">Ngày trả<input type="date" name="paid_at" required class="operator-input mt-1" value="{{ $line->paid_at?->toDateString() }}"></label>
                                        <label class="text-xs">Số chứng từ<input type="text" name="payment_reference" maxlength="100" class="operator-input mt-1" value="{{ $line->payment_reference }}"></label>
                                        <button type="submit" class="operator-button operator-button-secondary">Ghi đã trả</button>
                                    </form>
                                @endif
                            </details>
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection

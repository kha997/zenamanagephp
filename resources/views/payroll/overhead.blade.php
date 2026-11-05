@extends('layouts.operator')

@section('title', 'Chi phí chung ' . $month)
@section('page_title', 'Chi phí chung')

@section('content')
    {{-- GAP-114 N-1 / C-1 / P-1 / A-1: monthly company overhead and its split over projects by approved hours. --}}
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.');
        $closed = $period !== null && $period->isClosed();
    @endphp
    <x-ui.page-header :title="'Chi phí chung tháng ' . $month" description="Chi phí cố định của công ty cộng lương chưa vào công trình, chia cho công trình theo tỷ lệ giờ đã duyệt.">
        <x-ui.button-link :href="route('operator.payroll.index')" variant="secondary">Nhân sự & lương</x-ui.button-link>
        <x-ui.button-link :href="route('operator.payroll.pnl', ['month' => $month])" variant="secondary">Lời lỗ công trình</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif
    @if ($errors->any())
        <ul class="mb-3 space-y-1 text-sm text-red-700" data-testid="overhead-errors">
            @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul>
    @endif

    <x-ui.card>
        <form method="GET" action="{{ route('operator.payroll.overhead') }}" class="flex flex-wrap items-end gap-3 p-4">
            <label class="text-sm">Tháng <input type="month" name="month" class="operator-input mt-1" value="{{ $month }}"></label>
            <button type="submit" class="operator-button operator-button-secondary">Xem</button>
            <span class="text-sm {{ $closed ? 'text-emerald-700' : 'text-amber-700' }}">{{ $closed ? 'Đã chốt' : 'Chưa chốt' }}</span>
        </form>
    </x-ui.card>

    <x-ui.card title="Các khoản chi phí">
        @if ($lines->isEmpty())
            <x-ui.empty-state title="Chưa có khoản nào" description="Nhập chi phí cố định của tháng ở biểu mẫu bên dưới." />
        @else
            <x-ui.data-table :headers="['Loại', 'Mô tả', 'Số tiền', '']">
                @foreach ($lines as $line)
                    <tr data-testid="overhead-line-row">
                        <td class="text-sm">{{ $categories[$line->category] ?? $line->category }}</td>
                        <td class="text-sm">{{ $line->description }}</td>
                        <td class="text-sm font-semibold">{{ $money($line->amount) }}</td>
                        <td class="text-sm">
                            @if (!$closed)
                                <details>
                                    <summary class="operator-button operator-button-inline">Sửa</summary>
                                    <form method="POST" action="{{ route('operator.payroll.overhead.lines.update', $line->id) }}" class="mt-2 grid gap-2">
                                        @csrf
                                        <select name="category" class="operator-input">@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected($key === $line->category)>{{ $label }}</option>@endforeach</select>
                                        <input type="text" name="description" maxlength="255" required class="operator-input" value="{{ $line->description }}">
                                        <input type="number" name="amount" min="0.01" step="0.01" required class="operator-input" value="{{ (float) $line->amount }}">
                                        <button type="submit" class="operator-button operator-button-primary">Lưu</button>
                                    </form>
                                    <form method="POST" action="{{ route('operator.payroll.overhead.lines.delete', $line->id) }}" class="mt-2" onsubmit="return confirm('Xoá khoản này?')">
                                        @csrf
                                        <button type="submit" class="operator-button operator-button-secondary">Xoá</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
        @if (!$closed)
            <form method="POST" action="{{ route('operator.payroll.overhead.lines.store') }}" class="flex flex-wrap items-end gap-3 p-4" data-testid="overhead-line-form">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <label class="text-sm">Loại
                    <select name="category" class="operator-input mt-1">@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select>
                </label>
                <label class="text-sm">Mô tả <input type="text" name="description" maxlength="255" required class="operator-input mt-1" value="{{ old('description') }}"></label>
                <label class="text-sm">Số tiền <input type="number" name="amount" min="0.01" step="0.01" required class="operator-input mt-1" value="{{ old('amount') }}"></label>
                <button type="submit" class="operator-button operator-button-primary">Thêm</button>
            </form>
        @endif
    </x-ui.card>

    <x-ui.card :title="$closed ? 'Đã chia cho công trình' : 'Tạm tính chia cho công trình'">
        <div class="operator-form-grid p-4" data-testid="overhead-summary">
            <x-ui.field-value label="Các khoản đã nhập" :value="$money($summary['lines_total']) . ' ₫'" />
            <x-ui.field-value label="Lương chưa vào công trình" :value="$money($summary['labour_outside']) . ' ₫'" />
            <x-ui.field-value label="Tổng chi phí chung" :value="$money($summary['pool']) . ' ₫'" />
        </div>
        @if ($summary['rows'] === [])
            <x-ui.empty-state title="Chưa có gì để chia" description="Chưa có chi phí chung hoặc lương của tháng." />
        @else
            <x-ui.data-table :headers="['Công trình', 'Giờ', 'Chi phí chung']">
                @foreach ($summary['rows'] as $row)
                    <tr data-testid="overhead-allocation-row">
                        <td class="text-sm">{{ $row['project_id'] === null ? 'Chưa phân bổ' : ($projects[$row['project_id']] ?? $row['project_id']) }}</td>
                        <td class="text-sm">{{ rtrim(rtrim(number_format($row['minutes'] / 60, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="text-sm font-semibold">{{ $money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
        @if ($canClose && $period !== null)
            <div class="px-4 pb-4">
                @if (!$closed)
                    <form method="POST" action="{{ route('operator.payroll.overhead.close', $period->id) }}" data-testid="overhead-close-form" onsubmit="return confirm('Chốt chi phí chung tháng này?')">
                        @csrf
                        <button type="submit" class="operator-button operator-button-primary">Chốt chi phí chung</button>
                        <span class="ml-2 text-xs text-slate-500">Cần chốt lương tháng này trước.</span>
                    </form>
                @else
                    <form method="POST" action="{{ route('operator.payroll.overhead.reopen', $period->id) }}" class="flex items-end gap-2" data-testid="overhead-reopen-form">
                        @csrf
                        <label class="text-sm">Lý do mở lại <input type="text" name="reason" maxlength="500" required class="operator-input mt-1 w-64"></label>
                        <button type="submit" class="operator-button operator-button-secondary">Mở lại</button>
                    </form>
                @endif
            </div>
        @endif
    </x-ui.card>
@endsection

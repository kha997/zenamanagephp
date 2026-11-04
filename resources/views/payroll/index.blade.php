@extends('layouts.operator')

@section('title', 'Nhân sự & lương')
@section('page_title', 'Nhân sự & lương')

@section('content')
    {{-- GAP-109: monthly payroll list, payroll.manage only. --}}
    <x-ui.page-header title="Nhân sự & lương" description="Bảng lương từng tháng: chi phí công ty thực chịu và đơn giá giờ của từng người.">
        <x-ui.button-link :href="route('operator.payroll.staff.index')" variant="secondary">Danh sách nhân sự</x-ui.button-link>
    </x-ui.page-header>

    @if (session('success'))
        <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
    @endif

    <x-ui.card title="Tạo bảng lương tháng">
        @if ($errors->any())
            <ul class="mb-3 space-y-1 text-sm text-red-700" data-testid="payroll-errors">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        @endif
        <form method="POST" action="{{ route('operator.payroll.periods.store') }}" class="flex flex-wrap items-end gap-4 p-4" data-testid="payroll-period-create-form">
            @csrf
            <label class="text-sm">Tháng
                <input type="month" name="period" required class="operator-input mt-1" value="{{ old('period', $nextPeriod) }}">
            </label>
            <label class="text-sm">Số ngày công chuẩn
                <input type="number" name="working_days" min="1" max="31" required class="operator-input mt-1 w-24" value="{{ old('working_days', 26) }}">
            </label>
            <label class="text-sm">Giờ / ngày
                <input type="number" name="hours_per_day" min="1" max="24" step="0.5" class="operator-input mt-1 w-24" value="{{ old('hours_per_day', 8) }}">
            </label>
            <button type="submit" class="operator-button operator-button-primary">Tạo</button>
        </form>
    </x-ui.card>

    <x-ui.card>
        @if ($periods->isEmpty())
            <x-ui.empty-state title="Chưa có bảng lương" description="Tạo bảng lương tháng đầu tiên ở biểu mẫu phía trên." />
        @else
            @php $money = fn ($v) => number_format((float) $v, 0, ',', '.') . ' ₫'; @endphp
            <x-ui.data-table :headers="['Tháng', 'Trạng thái', 'Thực nhận', 'Tổng chi phí công ty', '']">
                @foreach ($periods as $row)
                    <tr data-testid="payroll-period-row">
                        <td class="font-semibold text-slate-900">{{ $row->period }}</td>
                        <td class="text-sm">{{ $row->isClosed() ? 'Đã chốt' : 'Nháp' }}</td>
                        <td class="text-sm">{{ $money($totals[$row->id]['net_pay']) }}</td>
                        <td class="text-sm font-semibold">{{ $money($totals[$row->id]['company_cost']) }}</td>
                        <td><a href="{{ route('operator.payroll.periods.show', $row->id) }}" class="operator-button operator-button-inline">Mở</a></td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection

@extends('layouts.operator')

@section('title', 'Lời lỗ công trình')
@section('page_title', 'Lời lỗ công trình')

@section('content')
    {{-- GAP-114 R-1 / D-1 / V-1: project P&L, payroll.manage only. --}}
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.');
        $tone = fn ($v) => (float) $v < 0 ? 'text-red-700' : 'text-emerald-700';
    @endphp
    <x-ui.page-header title="Lời lỗ công trình" description="Doanh thu đã thu − chi Ngân quỹ − nhân công − chi phí chung. Chi phí ghi nhận theo hợp đồng chỉ để đối chiếu, không cộng vào.">
        <x-ui.button-link :href="route('operator.payroll.overhead', ['month' => $month])" variant="secondary">Chi phí chung</x-ui.button-link>
        <x-ui.button-link :href="route('operator.payroll.pnl.export', ['month' => $month])" variant="secondary">Xuất CSV</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card>
        <form method="GET" action="{{ route('operator.payroll.pnl') }}" class="flex flex-wrap items-end gap-3 p-4">
            <label class="text-sm">Tháng <input type="month" name="month" class="operator-input mt-1" value="{{ $month }}"></label>
            <button type="submit" class="operator-button operator-button-secondary">Xem</button>
        </form>
        <div class="px-4 pb-4 text-sm" data-testid="pnl-status">
            @if ($data['closed'])
                <span class="font-semibold text-emerald-700">Đã chốt</span> — số đã cố định từ lúc chốt chi phí chung.
            @else
                <span class="font-semibold text-amber-700">Tạm tính</span> — tháng chưa chốt chi phí chung; số còn thay đổi và chưa cộng vào luỹ kế.
            @endif
        </div>
    </x-ui.card>

    <x-ui.card title="Tháng {{ $month }}">
        @if ($data['rows'] === [] && (float) $data['totals']['total_cost'] === 0.0 && (float) $data['totals']['revenue'] === 0.0)
            <x-ui.empty-state title="Chưa có số liệu" description="Chưa có doanh thu, chi phí hay giờ công trình trong tháng." />
        @else
            <x-ui.data-table :headers="['Công trình', 'Doanh thu đã thu', 'Chi Ngân quỹ', 'Nhân công', 'Chi phí chung', 'Lời / lỗ', 'Tỷ suất', 'Chi phí HĐ (đối chiếu)']">
                @foreach ($data['rows'] as $row)
                    <tr data-testid="pnl-row">
                        <td class="font-semibold text-slate-900">{{ $row['project'] }}</td>
                        <td class="text-sm">{{ $money($row['revenue']) }}</td>
                        <td class="text-sm">{{ $money($row['direct_cost']) }}</td>
                        <td class="text-sm">{{ $money($row['labour']) }}</td>
                        <td class="text-sm">{{ $money($row['overhead']) }}</td>
                        <td class="text-sm font-semibold {{ $tone($row['profit']) }}">{{ $money($row['profit']) }}</td>
                        <td class="text-sm">{{ $row['margin'] === null ? '—' : $row['margin'] . '%' }}</td>
                        <td class="text-sm text-slate-500">{{ $money($row['contract_expenses']) }}</td>
                    </tr>
                @endforeach
                <tr data-testid="pnl-total-row">
                    <td class="font-semibold">Toàn công ty</td>
                    <td class="text-sm font-semibold">{{ $money($data['totals']['revenue']) }}</td>
                    <td class="text-sm">{{ $money($data['totals']['direct_cost']) }}</td>
                    <td class="text-sm">{{ $money($data['totals']['labour']) }}</td>
                    <td class="text-sm">{{ $money($data['totals']['overhead']) }}@if ((float) $data['totals']['unallocated_overhead'] > 0) <span class="text-xs text-slate-500">(+ {{ $money($data['totals']['unallocated_overhead']) }} chưa phân bổ)</span>@endif</td>
                    <td class="text-sm font-semibold {{ $tone($data['totals']['profit']) }}">{{ $money($data['totals']['profit']) }}</td>
                    <td></td><td></td>
                </tr>
            </x-ui.data-table>
        @endif
    </x-ui.card>

    <x-ui.card title="Luỹ kế các tháng đã chốt">
        @if ($cumulative['rows'] === [])
            <x-ui.empty-state title="Chưa có tháng nào đã chốt" description="Luỹ kế chỉ cộng các tháng đã chốt chi phí chung." />
        @else
            <p class="px-4 pt-3 text-sm text-slate-600">Gồm {{ count($cumulative['months']) }} tháng: {{ implode(', ', $cumulative['months']) }}.</p>
            <x-ui.data-table :headers="['Công trình', 'Doanh thu đã thu', 'Tổng chi phí', 'Lời / lỗ theo tiền đã thu', 'Giá trị hợp đồng', 'Lời / lỗ dự kiến']">
                @foreach ($cumulative['rows'] as $row)
                    <tr data-testid="pnl-cumulative-row">
                        <td class="font-semibold text-slate-900">{{ $row['project'] }}</td>
                        <td class="text-sm">{{ $money($row['revenue']) }}</td>
                        <td class="text-sm">{{ $money($row['total_cost']) }}</td>
                        <td class="text-sm font-semibold {{ $tone($row['profit']) }}">{{ $money($row['profit']) }}</td>
                        <td class="text-sm">{{ $money($row['contract_value']) }}</td>
                        <td class="text-sm font-semibold {{ $tone($row['projected_profit']) }}">{{ $money($row['projected_profit']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection

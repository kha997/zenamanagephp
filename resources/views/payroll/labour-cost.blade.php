@extends('layouts.operator')

@section('title', 'Chi phí nhân công')
@section('page_title', 'Chi phí nhân công')

@section('content')
    {{-- GAP-113 D-1 / V-1: labour cost per project from payroll and approved hours, payroll.manage only. --}}
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', '.');
        $hours = fn (int $minutes) => rtrim(rtrim(number_format($minutes / 60, 2, ',', '.'), '0'), ',');
    @endphp
    <x-ui.page-header title="Chi phí nhân công" description="Chi phí công ty trả cho từng người, chia cho công trình theo giờ đã duyệt. Phần không vào công trình sẽ được phân bổ vào chi phí chung.">
        <x-ui.button-link :href="route('operator.payroll.index')" variant="secondary">Nhân sự & lương</x-ui.button-link>
        @if ($period)
            <x-ui.button-link :href="route('operator.payroll.labour-cost.export', $period->id)" variant="secondary">Xuất CSV</x-ui.button-link>
        @endif
    </x-ui.page-header>

    @if ($periods->isEmpty() || $period === null || $month === null)
        <x-ui.card>
            <x-ui.empty-state title="Chưa có bảng lương" description="Tạo bảng lương tháng ở trang Nhân sự & lương trước." />
        </x-ui.card>
    @else
        <x-ui.card>
            <form method="GET" action="{{ route('operator.payroll.labour-cost') }}" class="flex flex-wrap items-end gap-3 p-4" data-testid="labour-cost-period-form">
                <label class="text-sm">Tháng
                    <select name="period" class="operator-input mt-1" onchange="this.form.submit()">
                        @foreach ($periods as $row)
                            <option value="{{ $row->id }}" @selected($row->id === $period->id)>{{ $row->period }} — {{ $row->isClosed() ? 'đã chốt' : 'nháp' }}</option>
                        @endforeach
                    </select>
                </label>
                <noscript><button type="submit" class="operator-button operator-button-secondary">Xem</button></noscript>
            </form>
            <div class="px-4 pb-4 text-sm" data-testid="labour-cost-status">
                @if ($month['closed'])
                    <span class="font-semibold text-emerald-700">Đã chốt</span> — số đã cố định từ lúc chốt lương.
                @else
                    <span class="font-semibold text-amber-700">Tạm tính</span> — tháng chưa chốt lương; số còn thay đổi theo lương và giờ duyệt, chưa cộng vào luỹ kế công trình.
                @endif
            </div>
            <div class="operator-form-grid px-4 pb-4">
                <x-ui.field-value label="Vào công trình" :value="$money($month['project_total']) . ' ₫'" />
                <x-ui.field-value label="Chưa vào công trình (sang chi phí chung)" :value="$money($month['outside_total']) . ' ₫'" />
            </div>
        </x-ui.card>

        <x-ui.card title="Theo công trình — tháng {{ $period->period }}">
            @if ($month['projects'] === [])
                <x-ui.empty-state title="Chưa có giờ công trình đã duyệt" description="Chi phí công trình lấy từ giờ đã duyệt trong tháng." />
            @else
                <x-ui.data-table :headers="['Công trình', 'Giờ', 'Chi phí nhân công']">
                    @foreach ($month['projects'] as $row)
                        <tr data-testid="labour-cost-project-row">
                            <td class="font-semibold text-slate-900">{{ $row['project'] }}</td>
                            <td class="text-sm">{{ $hours($row['minutes']) }}</td>
                            <td class="text-sm font-semibold">{{ $money($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>

        <x-ui.card title="Chi tiết theo người">
            @if ($month['rows'] === [])
                <x-ui.empty-state title="Không có dòng nào" description="Chưa có chi phí công ty hoặc giờ đã duyệt trong tháng." />
            @else
                <x-ui.data-table :headers="['Công trình', 'Nhân sự', 'Giờ', 'Chi phí']">
                    @foreach ($month['rows'] as $row)
                        <tr data-testid="labour-cost-row">
                            <td class="text-sm">{{ $row['project'] ?? 'Chưa vào công trình' }}</td>
                            <td class="text-sm"><span class="font-semibold">{{ $row['code'] }}</span> {{ $row['name'] }}</td>
                            <td class="text-sm">{{ $hours($row['minutes']) }}</td>
                            <td class="text-sm">{{ $money($row['amount']) }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            @endif
        </x-ui.card>

        @if ($month['unpriced'] !== [])
            <x-ui.card title="Giờ chưa có đơn giá">
                <p class="px-4 pt-3 text-sm text-slate-600">Giờ đã duyệt của tài khoản không có dòng lương trong tháng (chưa nối với nhân sự hoặc không có trong bảng lương). Không tính thành tiền.</p>
                <x-ui.data-table :headers="['Người', 'Công trình', 'Giờ']">
                    @foreach ($month['unpriced'] as $row)
                        <tr data-testid="labour-cost-unpriced-row">
                            <td class="text-sm">{{ $row['person'] }}</td>
                            <td class="text-sm">{{ $row['project'] ?? 'Không thuộc công trình' }}</td>
                            <td class="text-sm">{{ $hours($row['minutes']) }}</td>
                        </tr>
                    @endforeach
                </x-ui.data-table>
            </x-ui.card>
        @endif
    @endif

    <x-ui.card title="Luỹ kế theo công trình (các tháng đã chốt)">
        @if ($cumulative === [])
            <x-ui.empty-state title="Chưa có tháng nào đã chốt" description="Luỹ kế chỉ cộng các tháng đã chốt lương." />
        @else
            <x-ui.data-table :headers="['Công trình', 'Số tháng', 'Giờ', 'Chi phí nhân công']">
                @foreach ($cumulative as $row)
                    <tr data-testid="labour-cost-cumulative-row">
                        <td class="font-semibold text-slate-900">{{ $row['project'] }}</td>
                        <td class="text-sm">{{ $row['months'] }}</td>
                        <td class="text-sm">{{ $hours($row['minutes']) }}</td>
                        <td class="text-sm font-semibold">{{ $money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </x-ui.data-table>
        @endif
    </x-ui.card>
@endsection

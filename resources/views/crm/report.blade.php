@extends('layouts.operator')

@section('title', 'Báo cáo kinh doanh')
@section('page_title', 'Báo cáo kinh doanh')

@section('content')
    <div class="space-y-6">
        <x-ui.card title="Doanh số theo tháng">
            @if (empty($monthlyRevenue))
                <p class="text-sm text-slate-500">Chưa có doanh số (chưa có cơ hội nào thắng).</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($monthlyRevenue as $month => $total)
                        <x-ui.field-value :label="$month" :value="number_format($total, 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Giá trị pipeline theo giai đoạn">
            @if (empty($pipelineByStage))
                <p class="text-sm text-slate-500">Chưa có dữ liệu pipeline.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($pipelineByStage as $stage => $total)
                        <x-ui.field-value :label="$stage" :value="number_format($total, 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Công nợ">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field-value label="Tổng công nợ" :value="number_format($outstandingDebt['total'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn" :value="number_format($outstandingDebt['overdue_total'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Số khoản quá hạn" :value="(string) $outstandingDebt['overdue_count']" />
            </div>
        </x-ui.card>

        <x-ui.card title="Hiệu quả sale">
            @if (empty($salesWinRate))
                <p class="text-sm text-slate-500">Chưa có dữ liệu.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($salesWinRate as $ownerId => $stats)
                        <x-ui.field-value :label="$ownerId" :value="$stats['won'] . '/' . $stats['total'] . ' (' . number_format($stats['rate'] * 100, 1) . '%)'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Hiệu quả gói dịch vụ">
            @if (empty($serviceCategoryPerformance))
                <p class="text-sm text-slate-500">Chưa có dữ liệu.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($serviceCategoryPerformance as $category => $stats)
                        <x-ui.field-value :label="$category" :value="$stats['won'] . '/' . $stats['total'] . ' (' . number_format($stats['rate'] * 100, 1) . '%) — TB ' . number_format($stats['avg_fee'], 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection

<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContractPayment;
use App\Services\BusinessKpiService;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class CrmReportController extends Controller
{
    public function index(BusinessKpiService $kpiService): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $outstandingDebt = $kpiService->outstandingDebt($tenantId);

        return view('crm.report', [
            'monthlyRevenue' => $kpiService->monthlyRevenue($tenantId),
            'pipelineByStage' => $kpiService->pipelineByStage($tenantId),
            'outstandingDebt' => $outstandingDebt,
            'outstandingDebtTotalMetric' => $this->computeOutstandingDebtTotalMetric($tenantId, $outstandingDebt['total']),
            'outstandingDebtOverdueMetric' => $this->computeOutstandingDebtOverdueMetric($tenantId, $outstandingDebt),
            'salesWinRate' => $kpiService->salesWinRate($tenantId),
            'serviceCategoryPerformance' => $kpiService->serviceCategoryPerformance($tenantId),
        ]);
    }

    private function hasAnyPaymentSchedule(string $tenantId): bool
    {
        return ContractPayment::query()->where('tenant_id', $tenantId)->exists();
    }

    private function computeOutstandingDebtTotalMetric(string $tenantId, float $total): MetricResult
    {
        $label = 'Giá trị theo lịch chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingDebt.total',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $total, $label) {
                if (!$this->hasAnyPaymentSchedule($tenantId)) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập.',
                    );
                }

                $asOf = ContractPayment::query()->where('tenant_id', $tenantId)->max('updated_at');

                return new MetricResult(
                    value: $total,
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LIMITED,
                    freshness: Freshness::UNKNOWN,
                    asOf: $asOf ? Carbon::parse($asOf) : null,
                    label: $label,
                    explanation: "Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần, nên số liệu này không phải công nợ thực tế đã xác nhận.",
                );
            },
        );
    }

    /**
     * @param array{total: float, overdue_total: float, overdue_count: int, aging: array{not_due: float, due_1_30: float, due_31_60: float, due_61_90: float, due_over_90: float}} $outstandingDebt
     */
    private function computeOutstandingDebtOverdueMetric(string $tenantId, array $outstandingDebt): MetricResult
    {
        $label = 'Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingDebt.overdue_total',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $outstandingDebt, $label) {
                if (!$this->hasAnyPaymentSchedule($tenantId)) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập.',
                    );
                }

                return new MetricResult(
                    value: $outstandingDebt['overdue_total'],
                    availability: Availability::AVAILABLE,
                    reliability: Reliability::LIMITED,
                    freshness: Freshness::UNKNOWN,
                    asOf: Carbon::now(),
                    label: $label,
                    explanation: 'Số liệu này chỉ tính các khoản đã tới hoặc quá hạn thanh toán theo lịch hợp đồng (dựa trên ngày đến hạn, không dựa vào nhãn trạng thái thủ công), chưa được đánh dấu \'đã thanh toán\'. Chưa phản ánh các khoản đã thu một phần.',
                );
            },
        );
    }
}

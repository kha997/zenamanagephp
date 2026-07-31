<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ContractPayment;
use App\Models\User;
use App\Services\BusinessKpiService;
use App\Services\ErrorEnvelopeService;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CrmReportController extends Controller
{
    public function index(BusinessKpiService $kpiService): View
    {
        /** @var User $user */
        $user = Auth::user();
        $tenantId = (string) $user->tenant_id;

        $debtMetrics = $this->computeOutstandingDebtMetrics($tenantId, $kpiService);

        return view('crm.report', [
            'monthlyRevenue' => $kpiService->monthlyRevenue($tenantId),
            'pipelineByStage' => $kpiService->pipelineByStage($tenantId),
            'outstandingDebt' => $debtMetrics['outstandingDebt'],
            'outstandingDebtTotalMetric' => $debtMetrics['totalMetric'],
            'outstandingDebtOverdueMetric' => $debtMetrics['overdueMetric'],
            'salesWinRate' => $kpiService->salesWinRate($tenantId),
            'serviceCategoryPerformance' => $kpiService->serviceCategoryPerformance($tenantId),
        ]);
    }

    /**
     * Tính outstandingDebt (legacy array) + cả 2 metric (total, overdue) từ
     * MỘT lần gọi BusinessKpiService::outstandingDebt() + MỘT lần kiểm tra
     * hasAnyPaymentSchedule() duy nhất (P2-C): cả 2 đều dùng chung bảng
     * contract_payments, trước đây mỗi metric tự gọi hasAnyPaymentSchedule()
     * riêng (2 lần) và outstandingDebt() luôn chạy TRƯỚC + NGOÀI guard, nên
     * một lỗi truy vấn thật sự sẽ 500 toàn trang trước khi kịp vào guard.
     *
     * @return array{outstandingDebt: array<string, mixed>, totalMetric: MetricResult, overdueMetric: MetricResult}
     */
    private function computeOutstandingDebtMetrics(string $tenantId, BusinessKpiService $kpiService): array
    {
        $totalLabel = 'Giá trị theo lịch chưa ghi nhận thanh toán';
        $overdueLabel = 'Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán';
        $emptyDebt = [
            'total' => 0.0,
            'overdue_total' => 0.0,
            'overdue_count' => 0,
            'aging' => [
                'not_due' => 0.0,
                'due_1_30' => 0.0,
                'due_31_60' => 0.0,
                'due_61_90' => 0.0,
                'due_over_90' => 0.0,
            ],
        ];

        try {
            $outstandingDebt = $kpiService->outstandingDebt($tenantId);
            $hasSchedule = ContractPayment::query()->where('tenant_id', $tenantId)->exists();
            $asOf = $hasSchedule
                ? ContractPayment::query()->where('tenant_id', $tenantId)->max('updated_at')
                : null;
        } catch (\Throwable $e) {
            Log::error('dashboard_metric_error', [
                'tenant_id' => $tenantId,
                'widget' => 'outstandingDebt',
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            $errorMetric = fn (string $label) => new MetricResult(
                value: null,
                availability: Availability::ERROR,
                reliability: Reliability::UNKNOWN,
                freshness: Freshness::UNKNOWN,
                asOf: null,
                label: $label,
                explanation: "Không thể tính được \"{$label}\" do lỗi truy vấn dữ liệu.",
            );

            return [
                'outstandingDebt' => $emptyDebt,
                'totalMetric' => $errorMetric($totalLabel),
                'overdueMetric' => $errorMetric($overdueLabel),
            ];
        }

        if (!$hasSchedule) {
            $noDataMetric = fn (string $label) => new MetricResult(
                value: null,
                availability: Availability::NO_DATA,
                reliability: Reliability::LIMITED,
                freshness: Freshness::UNKNOWN,
                asOf: null,
                label: $label,
                explanation: 'Chưa có lịch thanh toán nào được thiết lập.',
            );

            return [
                'outstandingDebt' => $outstandingDebt,
                'totalMetric' => $noDataMetric($totalLabel),
                'overdueMetric' => $noDataMetric($overdueLabel),
            ];
        }

        $asOfCarbon = $asOf ? Carbon::parse($asOf) : null;

        return [
            'outstandingDebt' => $outstandingDebt,
            'totalMetric' => new MetricResult(
                value: $outstandingDebt['total'],
                availability: Availability::AVAILABLE,
                reliability: Reliability::LIMITED,
                freshness: Freshness::UNKNOWN,
                asOf: $asOfCarbon,
                label: $totalLabel,
                explanation: "Số liệu này cộng tất cả các khoản thanh toán theo lịch hợp đồng chưa được đánh dấu 'đã thanh toán', kể cả các khoản chưa tới hạn. Hệ thống hiện chưa ghi nhận thanh toán từng phần, nên số liệu này không phải công nợ thực tế đã xác nhận.",
            ),
            'overdueMetric' => new MetricResult(
                value: $outstandingDebt['overdue_total'],
                availability: Availability::AVAILABLE,
                reliability: Reliability::LIMITED,
                freshness: Freshness::UNKNOWN,
                asOf: Carbon::now(),
                label: $overdueLabel,
                explanation: 'Số liệu này chỉ tính các khoản đã tới hoặc quá hạn thanh toán theo lịch hợp đồng (dựa trên ngày đến hạn, không dựa vào nhãn trạng thái thủ công), chưa được đánh dấu \'đã thanh toán\'. Chưa phản ánh các khoản đã thu một phần.',
            ),
        ];
    }
}

<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\ErrorEnvelopeService;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PortalDashboardController extends Controller
{
    public function index(string $tenantSlug): View
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $projectIds = Opportunity::query()
            ->where('tenant_id', $tenant->id)
            ->where('account_id', $account->id)
            ->whereNotNull('converted_project_id')
            ->pluck('converted_project_id')
            ->unique()
            ->values();

        $projects = Project::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'status']);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'review_status']);

        $documents = Document::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get(['id', 'project_id', 'name', 'title', 'created_at']);

        $contracts = Contract::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->get(['id', 'project_id', 'code', 'total_value', 'currency', 'status']);

        $outstandingBalanceMetric = $this->computeOutstandingBalanceMetric($tenant->id, $contracts->pluck('id')->all());
        $outstandingBalance = $outstandingBalanceMetric->value ?? 0.0;

        try {
            $paymentSchedule = ContractPayment::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('contract_id', $contracts->pluck('id'))
                ->where('status', '!=', ContractPayment::STATUS_PAID)
                ->orderBy('due_date')
                ->get(['id', 'contract_id', 'name', 'amount', 'due_date', 'status']);
        } catch (\Throwable $e) {
            Log::error('dashboard_metric_error', [
                'tenant_id' => (string) $tenant->id,
                'widget' => 'paymentSchedule',
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            $paymentSchedule = new EloquentCollection();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Quote> $quotes */
        $quotes = Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->where('quotes.tenant_id', $tenant->id)
            ->where('opportunities.account_id', $account->id)
            ->where('quotes.status', '!=', Quote::STATUS_DRAFT)
            ->orderByDesc('quotes.sent_at')
            ->select('quotes.*')
            ->get();

        return view('portal.dashboard', [
            'tenant' => $tenant,
            'projects' => $projects,
            'designItems' => $designItems,
            'documents' => $documents,
            'contracts' => $contracts,
            'outstandingBalance' => $outstandingBalance,
            'outstandingBalanceMetric' => $outstandingBalanceMetric,
            'paymentSchedule' => $paymentSchedule,
            'quotes' => $quotes,
        ]);
    }

    /**
     * @param array<int, string> $contractIds
     */
    private function computeOutstandingBalanceMetric(string $tenantId, array $contractIds): MetricResult
    {
        $label = 'Giá trị theo lịch chưa ghi nhận thanh toán';

        return MetricGuard::wrap(
            'outstandingBalance',
            ['tenant_id' => $tenantId],
            $label,
            function () use ($tenantId, $contractIds, $label) {
                $scheduleCount = ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('contract_id', $contractIds)
                    ->count();

                if ($scheduleCount === 0) {
                    return new MetricResult(
                        value: null,
                        availability: Availability::NO_DATA,
                        reliability: Reliability::LIMITED,
                        freshness: Freshness::UNKNOWN,
                        asOf: null,
                        label: $label,
                        explanation: 'Chưa có lịch thanh toán nào được thiết lập cho dự án này.',
                    );
                }

                $sum = (float) ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('contract_id', $contractIds)
                    ->where('status', '!=', ContractPayment::STATUS_PAID)
                    ->sum('amount');

                $asOf = ContractPayment::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('contract_id', $contractIds)
                    ->max('updated_at');

                return new MetricResult(
                    value: $sum,
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
}

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
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

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

        $outstandingBalance = (float) ContractPayment::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('status', '!=', ContractPayment::STATUS_PAID)
            ->sum('amount');

        $paymentSchedule = ContractPayment::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('status', '!=', ContractPayment::STATUS_PAID)
            ->orderBy('due_date')
            ->get(['id', 'contract_id', 'name', 'amount', 'due_date', 'status']);

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
            'paymentSchedule' => $paymentSchedule,
            'quotes' => $quotes,
        ]);
    }
}

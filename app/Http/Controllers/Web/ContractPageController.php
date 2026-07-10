<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\ContractController as ApiContractController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Contract;
use App\Models\Project;
use App\Services\DeliverablePdfExportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class ContractPageController extends Controller
{
    use DelegatesToApiControllers;

    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = Contract::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return view('contracts.index', [
            'contracts' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('contracts.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, ApiContractController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:draft,active,closed,cancelled'],
            'currency' => ['nullable', 'string', 'size:3'],
            'total_value' => ['nullable', 'numeric', 'min:0'],
            'signed_at' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $projectId = (string) $validated['project_id'];
        unset($validated['project_id']);
        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated), $projectId);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        $payload = $response->getData(true);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $contractId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.contracts.show', $contractId)
                ->with('success', 'Tạo hợp đồng thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(Request $request, string $id, ApiContractController $apiController): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->with('project:id,tenant_id,name,code')
            ->findOrFail($id);

        $summary = null;
        $summaryUnavailableMessage = null;

        try {
            $summaryResponse = $apiController->costSummary(
                $this->buildApiRequest($request),
                (string) $contract->project_id,
                (string) $contract->id
            );

            if ($summaryResponse->getStatusCode() >= 200 && $summaryResponse->getStatusCode() < 300) {
                $summary = data_get($summaryResponse->getData(true), 'data.summary');
            } else {
                $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
            }
        } catch (AuthorizationException|Throwable) {
            $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
        }

        $hasDrift = false;
        if ($contract->source_opportunity_id) {
            $sourceOpportunity = \App\Models\Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->find($contract->source_opportunity_id);

            if ($sourceOpportunity) {
                $snapshot = $sourceOpportunity->external_quote_snapshot ?? [];
                $hasDrift = $contract->source_quote_id !== $sourceOpportunity->external_quote_id
                    || $contract->source_quote_revision !== ($snapshot['revision'] ?? null);
            }
        }

        return view('contracts.show', [
            'contract' => $contract,
            'summary' => $summary,
            'summaryUnavailableMessage' => $summaryUnavailableMessage,
            'hasQuoteDrift' => $hasDrift,
        ]);
    }

    public function downloadPdf(Request $request, string $id, ApiContractController $apiController, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        try {
            $response = $apiController->pdf(
                $this->buildApiRequest($request),
                (string) $contract->project_id,
                (string) $contract->id,
                $pdfService
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể tạo PDF hợp đồng vào lúc này.');
        }

        if ($response instanceof JsonResponse) {
            return $this->handleErrorResponse($response);
        }

        return $response;
    }
}

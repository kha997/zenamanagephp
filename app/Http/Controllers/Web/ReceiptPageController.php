<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\ContractController as ApiContractController;
use App\Http\Controllers\Api\MaterialReceiptController as ApiMaterialReceiptController;
use App\Http\Controllers\Api\MaterialReceiptLineController as ApiMaterialReceiptLineController;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialRequest;
use App\Models\Project;
use App\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ReceiptPageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MaterialReceipt::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('receipts.index', [
            'receipts' => MaterialReceipt::query()
                ->where('tenant_id', $tenantId)
                ->with([
                    'project:id,tenant_id,name,code',
                    'contract:id,tenant_id,project_id,code,title',
                    'materialRequest:id,project_id,request_number,status',
                ])
                ->orderByDesc('receipt_date')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MaterialReceipt::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('receipts.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'vendors' => Vendor::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'contracts' => Contract::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('title')
                ->get(['id', 'tenant_id', 'project_id', 'code', 'title']),
            'materialRequests' => MaterialRequest::query()
                ->with('project:id,tenant_id,name,code')
                ->whereHas('project', function ($query) use ($tenantId): void {
                    $query->where('tenant_id', $tenantId);
                })
                ->orderByDesc('created_at')
                ->get(['id', 'project_id', 'request_number', 'status']),
        ]);
    }

    public function store(Request $request, ApiMaterialReceiptController $apiController): RedirectResponse
    {
        $this->authorize('create', MaterialReceipt::class);

        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'vendor_id' => ['nullable', 'string'],
            'contract_id' => ['nullable', 'string'],
            'material_request_id' => ['nullable', 'string'],
            'receipt_number' => ['required', 'string'],
            'receipt_date' => ['required', 'date'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            $receiptId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.receipts.show', $receiptId)
                ->with('success', 'Tạo phiếu nhập thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(Request $request, string $receipt, ApiContractController $contractController): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $receiptModel = MaterialReceipt::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'vendor:id,tenant_id,name,code',
                'contract:id,tenant_id,project_id,code,title',
                'materialRequest:id,project_id,request_number,status',
                'lines.material:id,tenant_id,code,name,unit',
            ])
            ->findOrFail($receipt);

        $this->authorize('view', $receiptModel);

        $summary = null;
        $summaryUnavailableMessage = null;
        if ($receiptModel->contract_id) {
            try {
                $summaryResponse = $contractController->costSummary(
                    $this->buildApiRequest($request),
                    (string) $receiptModel->project_id,
                    (string) $receiptModel->contract_id
                );

                if ($summaryResponse->getStatusCode() >= 200 && $summaryResponse->getStatusCode() < 300) {
                    $summary = data_get($summaryResponse->getData(true), 'data.summary');
                } else {
                    $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
                }
            } catch (AuthorizationException|Throwable) {
                $summary = null;
                $summaryUnavailableMessage = 'Không thể tải tổng hợp chi phí hợp đồng vào lúc này.';
            }
        }

        return view('receipts.show', [
            'receipt' => $receiptModel,
            'materials' => Material::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'code', 'name', 'unit']),
            'summary' => $summary,
            'summaryUnavailableMessage' => $summaryUnavailableMessage,
        ]);
    }

    public function storeLine(
        Request $request,
        string $receipt,
        ApiMaterialReceiptLineController $apiController
    ): RedirectResponse {
        $validated = $request->validate([
            'material_id' => ['required', 'string'],
            'quantity_received' => ['required', 'numeric'],
            'unit_cost' => ['nullable', 'numeric'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated), $receipt);
        } catch (AuthorizationException) {
            return redirect()
                ->route('operator.receipts.show', $receipt)
                ->withInput()
                ->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return redirect()
                ->route('operator.receipts.show', $receipt)
                ->withInput()
                ->with('error', 'Không thể thêm dòng vật tư.');
        }

        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return redirect()
                ->route('operator.receipts.show', $receipt)
                ->with('success', 'Đã thêm dòng vật tư');
        }

        if ($status === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return redirect()
                ->route('operator.receipts.show', $receipt)
                ->withErrors($payload['data'])
                ->withInput();
        }

        return redirect()
            ->route('operator.receipts.show', $receipt)
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể thêm dòng vật tư.'));
    }

    private function buildApiRequest(Request $request, array $payload = []): Request
    {
        $apiRequest = Request::create(
            $request->fullUrl(),
            $request->method(),
            $payload,
            $request->cookies->all(),
            [],
            $request->server->all()
        );

        $apiRequest->headers->replace($request->headers->all());

        foreach ($request->attributes->all() as $key => $value) {
            $apiRequest->attributes->set($key, $value);
        }

        $apiRequest->setLaravelSession($request->session());
        $apiRequest->setUserResolver(static fn () => $request->user());

        return $apiRequest;
    }

    private function handleErrorResponse(JsonResponse $response): RedirectResponse
    {
        $payload = $response->getData(true);

        if ($response->getStatusCode() === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return back()->withErrors($payload['data'])->withInput();
        }

        return back()
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
    }
}

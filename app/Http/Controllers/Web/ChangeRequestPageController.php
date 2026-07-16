<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\ChangeRequestController as ApiChangeRequestController;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Project;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class ChangeRequestPageController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = ChangeRequest::query()
            ->where('tenant_id', $tenantId)
            ->with(['project:id,tenant_id,name,code', 'requestedBy:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('change_number', 'like', "%{$search}%");
            });
        }

        return view('change-requests.index', [
            'changeRequests' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('change-requests.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, ApiChangeRequestController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'change_type' => ['required', 'in:scope,cost,schedule,quality,design,other'],
            'impact_analysis' => ['required', 'string'],
            'cost_impact' => ['nullable', 'numeric', 'min:0'],
            'schedule_impact_days' => ['nullable', 'integer', 'min:0'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'justification' => ['required', 'string'],
            'alternatives_considered' => ['nullable', 'string'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

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
            $changeRequestId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.change-requests.show', $changeRequestId)
                ->with('success', 'Tạo yêu cầu thay đổi thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $changeRequest = ChangeRequest::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'requestedBy:id,name',
                'approvedBy:id,name',
            ])
            ->findOrFail($id);

        return view('change-requests.show', ['changeRequest' => $changeRequest]);
    }

    public function submit(Request $request, string $id, ApiChangeRequestController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->submit($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.change-requests.show', $id), 'Đã gửi duyệt');
    }

    public function approve(Request $request, string $id, ApiChangeRequestController $apiController): RedirectResponse
    {
        $payload = $request->validate([
            'approval_comments' => ['nullable', 'string'],
        ]);

        try {
            $response = $apiController->approve($this->buildApiRequest($request, array_filter($payload)), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.change-requests.show', $id), 'Đã phê duyệt');
    }

    public function reject(Request $request, string $id, ApiChangeRequestController $apiController): RedirectResponse
    {
        $payload = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        try {
            $response = $apiController->reject($this->buildApiRequest($request, $payload), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.change-requests.show', $id), 'Đã từ chối');
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

    private function handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage): RedirectResponse
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return redirect($successUrl)->with('success', $successMessage);
        }

        return $this->handleErrorResponse($response);
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

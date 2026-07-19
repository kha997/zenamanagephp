<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\MaterialRequestController as ApiMaterialRequestController;
use App\Http\Controllers\Controller;
use App\Models\MaterialRequest;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class MaterialRequestPageController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MaterialRequest::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        $materialRequests = MaterialRequest::query()
            ->with('project:id,tenant_id,name,code')
            ->whereHas('project', function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->orderByDesc('created_at')
            ->get();

        return view('material-requests.index', [
            'materialRequests' => $materialRequests,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MaterialRequest::class);

        $tenantId = (string) Auth::user()?->tenant_id;

        return view('material-requests.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function store(Request $request, ApiMaterialRequestController $apiController): RedirectResponse
    {
        $this->authorize('create', MaterialRequest::class);

        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'description' => ['required', 'string'],
            'estimated_cost' => ['nullable', 'numeric'],
            'required_date' => ['nullable', 'date'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest($request, $validated));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            route('operator.material-requests.index'),
            'Tạo yêu cầu thành công'
        );
    }

    public function submit(Request $request, string $id, ApiMaterialRequestController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->submit($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.material-requests.index'), 'Đã gửi duyệt');
    }

    public function approve(Request $request, string $id, ApiMaterialRequestController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->approve($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.material-requests.index'), 'Đã phê duyệt');
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
        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            return redirect($successUrl)->with('success', $successMessage);
        }

        if ($status === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return back()->withErrors($payload['data'])->withInput();
        }

        return back()
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
    }
}

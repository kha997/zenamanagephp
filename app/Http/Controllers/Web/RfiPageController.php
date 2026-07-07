<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\RfiController as ApiRfiController;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class RfiPageController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'assignedTo:id,name',
            ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('rfi_number', 'like', "%{$search}%");
            });
        }

        return view('rfis.index', [
            'rfis' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('rfis.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'assignees' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name']),
        ]);
    }

    public function store(Request $request, ApiRfiController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'due_date' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'drawing_reference' => ['nullable', 'string', 'max:255'],
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
            $rfiId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.rfis.show', $rfiId)
                ->with('success', 'Tạo RFI thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $rfi = Rfi::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'assignedTo:id,name',
                'createdBy:id,name',
                'respondedBy:id,name',
            ])
            ->findOrFail($id);

        return view('rfis.show', ['rfi' => $rfi]);
    }

    public function respond(Request $request, string $id, ApiRfiController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', 'string'],
        ]);

        $validated['status'] = 'answered';

        try {
            $response = $apiController->respond($this->buildApiRequest($request, $validated), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.rfis.show', $id), 'Đã gửi phản hồi');
    }

    public function close(Request $request, string $id, ApiRfiController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->close($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.rfis.show', $id), 'Đã đóng RFI');
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
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
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

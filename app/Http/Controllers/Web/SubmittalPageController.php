<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Http\Controllers\Api\SubmittalController as ApiSubmittalController;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Vendor;
use App\Services\SubmittalLifecycleService;
use App\Support\SubmittalContentRules;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class SubmittalPageController extends Controller
{
    public function __construct(private SubmittalLifecycleService $lifecycle)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $query = Submittal::query()
            ->where('tenant_id', $tenantId)
            ->with(['project:id,tenant_id,name,code', 'submittedBy:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('submittal_number', 'like', "%{$search}%")
                    ->orWhere('specification_section', 'like', "%{$search}%");
            });
        }

        return view('submittals.index', [
            'submittals' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        return view('submittals.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
            'vendors' => Vendor::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'code', 'name']),
        ]);
    }

    public function store(Request $request, ApiSubmittalController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'submittal_type' => ['required', 'in:shop_drawing,material_sample,product_data,test_report,other'],
            'specification_section' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'contractor' => ['nullable', 'string', 'max:255',
                Rule::exists('vendors', 'name')->where('tenant_id', (string) Auth::user()?->tenant_id),
            ],
            'manufacturer' => ['nullable', 'string', 'max:255',
                Rule::exists('vendors', 'name')->where('tenant_id', (string) Auth::user()?->tenant_id),
            ],
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
            $submittalId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.submittals.show', $submittalId)
                ->with('success', 'Tạo submittal thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $submittal = $this->submittalForTenant($id);
        $tenantId = (string) Auth::user()?->tenant_id;

        return view('submittals.show', [
            'submittal' => $submittal,
            'vendors' => Vendor::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $submittal = $this->submittalForTenant($id);
        $this->authorize('update', $submittal);

        $tenantId = (string) Auth::user()?->tenant_id;
        $rules = SubmittalContentRules::rules();

        if ($request->input('contractor') !== $submittal->contractor) {
            $rules['contractor'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
        }
        if ($request->input('manufacturer') !== $submittal->manufacturer) {
            $rules['manufacturer'][] = Rule::exists('vendors', 'name')->where('tenant_id', $tenantId);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'submittalUpdate')->withInput();
        }

        $data = $request->only([
            'title', 'description', 'submittal_type', 'specification_section',
            'due_date', 'contractor', 'manufacturer',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->lifecycle->updateContent($submittal, $data, ['actor_user_id' => $user->id]);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return back()->with('error', 'Không thể sửa nội dung ở trạng thái hiện tại.');
        }

        return redirect()->route('operator.submittals.show', $id)->with('success', 'Đã lưu thay đổi');
    }

    public function startRevision(Request $request, string $id): RedirectResponse
    {
        $submittal = $this->submittalForTenant($id);
        $this->authorize('startRevision', $submittal);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();
            $this->lifecycle->startRevision($submittal, ['actor_user_id' => $user->id]);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return back()->with('error', 'Chỉ hồ sơ bị từ chối mới mở lại để sửa được.');
        }

        return redirect()->route('operator.submittals.show', $id)->with('success', 'Đã mở lại để sửa');
    }

    public function submit(Request $request, string $id, ApiSubmittalController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->submit($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.submittals.show', $id), 'Đã gửi duyệt', 'submittalResubmit');
    }

    public function approve(Request $request, string $id, ApiSubmittalController $apiController): RedirectResponse
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

        return $this->handleMutationResponse($response, route('operator.submittals.show', $id), 'Đã phê duyệt');
    }

    public function reject(Request $request, string $id, ApiSubmittalController $apiController): RedirectResponse
    {
        $payload = $request->validate([
            'rejection_reason' => ['required', 'string'],
            'rejection_comments' => ['nullable', 'string'],
        ]);

        try {
            $response = $apiController->reject($this->buildApiRequest($request, array_filter($payload)), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.submittals.show', $id), 'Đã từ chối');
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

    private function submittalForTenant(string $id): Submittal
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        return Submittal::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'project:id,tenant_id,name,code',
                'submittedBy:id,name',
                'reviewedBy:id,name',
                'rejectedBy:id,name',
            ])
            ->findOrFail($id);
    }

    private function handleMutationResponse(JsonResponse $response, string $successUrl, string $successMessage, ?string $errorBag = null): RedirectResponse
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return redirect($successUrl)->with('success', $successMessage);
        }

        return $this->handleErrorResponse($response, $errorBag);
    }

    private function handleErrorResponse(JsonResponse $response, ?string $errorBag = null): RedirectResponse
    {
        $payload = $response->getData(true);

        if ($response->getStatusCode() === 422 && isset($payload['data']) && is_array($payload['data'])) {
            return back()->withErrors($payload['data'], $errorBag ?? 'default')->withInput();
        }

        return back()
            ->withInput()
            ->with('error', (string) ($payload['message'] ?? 'Không thể xử lý yêu cầu.'));
    }
}

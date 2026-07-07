<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\InspectionController as ApiInspectionController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class InspectionPageController extends Controller
{
    use DelegatesToApiControllers;

    public function index(Request $request): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $query = QcInspection::query()
            ->where('tenant_id', $tenantId)
            ->with(['qcPlan:id,title,project_id', 'inspector:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        return view('inspections.index', [
            'inspections' => $query->orderByDesc('inspection_date')->paginate(20)->withQueryString(),
            'currentStatus' => (string) $request->query('status', ''),
            'currentSearch' => (string) $request->query('search', ''),
        ]);
    }

    public function create(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('inspections.create', [
            'qcPlans' => QcPlan::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('title')
                ->get(['id', 'tenant_id', 'title']),
            'inspectors' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name']),
        ]);
    }

    public function store(Request $request, ApiInspectionController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'qc_plan_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'inspection_date' => ['required', 'date'],
            'inspector_id' => ['required', 'string'],
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

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $inspectionId = (string) data_get($payload, 'data.id');

            return redirect()
                ->route('operator.inspections.show', $inspectionId)
                ->with('success', 'Tạo phiên kiểm định thành công');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $inspection = QcInspection::query()
            ->where('tenant_id', $tenantId)
            ->with(['qcPlan:id,title,project_id', 'inspector:id,name'])
            ->findOrFail($id);

        return view('inspections.show', ['inspection' => $inspection]);
    }

    public function conduct(Request $request, string $id, ApiInspectionController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'findings' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->conduct($this->buildApiRequest($request, $validated), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.inspections.show', $id), 'Đã ghi nhận kiểm định');
    }

    public function complete(Request $request, string $id, ApiInspectionController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'findings' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        $validated = array_filter($validated, static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $apiController->complete($this->buildApiRequest($request, $validated), $id);
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.inspections.show', $id), 'Đã hoàn tất kiểm định');
    }
}

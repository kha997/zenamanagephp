<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\SiteDiaryController as ApiSiteDiaryController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Project;
use App\Models\SiteDiary;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class SiteDiaryPageController extends Controller
{
    use DelegatesToApiControllers;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SiteDiary::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        $query = SiteDiary::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name,code', 'creator:id,name');

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->query('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        $siteDiaries = $query
            ->orderByDesc('diary_date')
            ->orderByDesc('created_at')
            ->get();

        return view('site-diaries.index', [
            'siteDiaries' => $siteDiaries,
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SiteDiary::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        return view('site-diaries.create', [
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $siteDiary = SiteDiary::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name,code', 'creator:id,name', 'approver:id,name')
            ->findOrFail($id);

        $this->authorize('view', $siteDiary);

        return view('site-diaries.show', [
            'siteDiary' => $siteDiary,
        ]);
    }

    public function store(Request $request, ApiSiteDiaryController $apiController): RedirectResponse
    {
        $this->authorize('create', SiteDiary::class);

        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'diary_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:100'],
            'temperature' => ['nullable', 'string', 'max:50'],
            'manpower_count' => ['nullable', 'integer', 'min:0'],
            'work_performed' => ['required', 'string'],
            'equipment_used' => ['nullable', 'string'],
            'materials_delivered' => ['nullable', 'string'],
            'safety_notes' => ['nullable', 'string'],
            'visitors' => ['nullable', 'string'],
            'delays_issues' => ['nullable', 'string'],
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
            route('operator.site-diaries.index'),
            'Tạo nhật ký công trường thành công'
        );
    }

    public function submit(Request $request, string $id, ApiSiteDiaryController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->submit($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.site-diaries.index'), 'Đã gửi duyệt nhật ký');
    }

    public function approve(Request $request, string $id, ApiSiteDiaryController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->approve($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.site-diaries.index'), 'Đã phê duyệt nhật ký');
    }
}

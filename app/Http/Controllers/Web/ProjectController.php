<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;
use App\Models\Project as AppProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;


use App\Http\Controllers\Controller; // Thêm import này
use App\Models\User;
use App\Services\DocumentChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\Project;
use Src\RBAC\Middleware\RBACMiddleware;

/**
 * Controller xử lý các hoạt động CRUD cho Project
 * 
 * @package Src\CoreProject\Controllers
 */
class ProjectController extends Controller // Thêm extends Controller
{
    use \App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;

    // Xóa constructor middleware
    // public function __construct()
    // {
    //     $this->middleware(RBACMiddleware::class);
    // }

    // Thay vào đó, áp dụng middleware trong routes
    /**
     * Lấy danh sách projects
     *
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * Lấy danh sách projects với proper validation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index()
    {
        $projects = Project::with(['tenant', 'users', 'tasks.assignee'])
            ->whereTenantId(Auth::user()->tenant_id)
            ->paginate(15);
        
        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        $user = Auth::user();

        return view('projects.create', [
            'currentRoute' => 'projects',
            'user' => $user,
            'tenant' => $user?->tenant,
            'users' => User::query()
                ->where('tenant_id', $user?->tenant_id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function edit(string $projectId): View
    {
        $user = Auth::user();

        $projectData = Project::query()
            ->whereTenantId($user?->tenant_id)
            ->findOrFail($projectId);

        $users = User::query()
            ->where('tenant_id', $user?->tenant_id)
            ->select(['id', 'name'])
            ->get();

        return view('projects.edit', [
            'currentRoute' => 'projects',
            'user' => $user,
            'tenant' => $user?->tenant,
            'projectData' => $projectData,
            'users' => $users,
        ]);
    }

    public function store(
        Request $request,
        \App\Http\Controllers\Api\ProjectController $apiController
    ): \Illuminate\Http\RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'budget_planned' => ['nullable', 'numeric', 'min:0'],
            'pm_id' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
        ]);

        try {
            $apiRequest = \App\Http\Requests\ProjectFormRequest::createFrom(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null))
            );
            $apiRequest->setContainer(app())->setRedirector(app('redirect'));
            $apiRequest->validateResolved();

            $response = $apiController->store($apiRequest);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('app.projects'), 'Đã tạo dự án');
    }

    /**
     * Lấy thông tin chi tiết một project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function show(string $projectId): View
    {
        try {
            $user = Auth::user();

            $project = AppProject::query()
                ->with([
                    'manager',
                    'client',
                    'tasks',
                ])
                ->where('tenant_id', $user?->tenant_id)
                ->findOrFail($projectId);

            $documentChecklist = $user?->hasPermission('work.view')
                ? (new DocumentChecklistService())->buildReport($project)
                : null;

            return view('projects.show', [
                'project' => $project,
                'documentChecklist' => $documentChecklist,
            ]);
        } catch (\Throwable $e) {
            abort(404, 'Dự án không tồn tại.');
        }
    }

    public function update(
        Request $request,
        string $projectId,
        \App\Http\Controllers\Api\ProjectController $apiController
    ): \Illuminate\Http\RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'budget_planned' => ['nullable', 'numeric', 'min:0'],
            'pm_id' => ['nullable', 'string'],
            'client_id' => ['nullable', 'string'],
        ]);

        try {
            $apiRequest = \App\Http\Requests\ProjectFormRequest::createFrom(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null))
            );
            $apiRequest->setContainer(app())->setRedirector(app('redirect'));
            $apiRequest->validateResolved();

            $response = $apiController->update($apiRequest, $projectId);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        } catch (\Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse(
            $response,
            '/app/projects/' . $projectId,
            'Đã cập nhật dự án'
        );
    }

    /**
     * Xóa project
     *
     * @param int $projectId
     * @return JsonResponse
     */
    public function destroy(string $projectId): JsonResponse // Đổi từ int thành string
    {
        try {
            $project = Project::findOrFail($projectId);

            // Kiểm tra xem project có components không
            if ($project->components()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa dự án này vì nó có các component liên kết. Vui lòng xóa các component trước.',
                    400
                );
            }

            // Kiểm tra xem project có tasks không
            if ($project->tasks()->exists()) {
                return JSendResponse::error(
                    'Không thể xóa dự án này vì nó có các task liên kết. Vui lòng xóa các task trước.',
                    400
                );
            }

            $project->delete();

            // Dispatch event
            event(new \Src\CoreProject\Events\ProjectUpdated($project, $oldData, ['deleted']));

            return JSendResponse::success([
                'message' => 'Dự án đã được xóa thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa dự án: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tính toán lại progress của project
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function recalculateProgress(string $projectId): JsonResponse // Đổi từ int thành string
    {
        try {
            $project = Project::findOrFail($projectId);
            $project->recalculateProgress();
            $project->load(['rootComponents', 'tasks']);

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Tiến độ dự án đã được tính toán lại.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán lại tiến độ: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tính toán lại chi phí thực tế của project
     *
     * @param string $projectId
     * @return JsonResponse
     */
    public function recalculateActualCost(string $projectId): JsonResponse // Đổi từ int thành string
    {
        try {
            $project = Project::findOrFail($projectId);
            $project->recalculateActualCost();
            $project->load(['rootComponents', 'tasks']);

            return JSendResponse::success([
                'project' => new ProjectResource($project),
                'message' => 'Chi phí thực tế đã được tính toán lại.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Dự án không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tính toán lại chi phí: ' . $e->getMessage(), 500);
        }
    }
}

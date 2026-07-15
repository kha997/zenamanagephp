<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;
use App\Models\Project as AppProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;


use App\Http\Controllers\Controller; // Thêm import này
use App\Models\DeliverableTemplate;
use App\Models\User;
use App\Services\DeliverablePdfExportService;
use App\Services\DeliverableTemplateVersionService;
use App\Services\DocumentChecklistService;
use App\Services\DocumentContext\DocumentContextRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
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

            $designItems = \App\Models\DesignItem::query()
                ->where('project_id', (string) $project->id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();

            // Load tasks qua model canonical App\Models\Task — relation
            // $project->tasks() trỏ về Src\CoreProject\Models\Task (legacy,
            // không có relation assignee) nên không dùng cho khối này.
            $sectionTasks = \App\Models\Task::query()
                ->where('tenant_id', (string) $user?->tenant_id)
                ->where('project_id', (string) $project->id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();

            $blockedItems = collect()
                ->concat($designItems->whereNotNull('blocked_at')->map(fn ($i) => [
                    'type' => 'Hạng mục thiết kế',
                    'name' => $i->name,
                    'note' => $i->blocker_note,
                    'blocked_at' => $i->blocked_at,
                ]))
                ->concat($sectionTasks->whereNotNull('blocked_at')->map(fn ($t) => [
                    'type' => 'Công việc',
                    'name' => $t->title ?? $t->name,
                    'note' => $t->blocker_note,
                    'blocked_at' => $t->blocked_at,
                ]))
                ->sortByDesc('blocked_at')
                ->values();

            $contracts = \App\Models\Contract::query()
                ->where('project_id', (string) $project->id)
                ->withSum(['payments as paid_total' => fn ($q) => $q->where('status', \App\Models\ContractPayment::STATUS_PAID)], 'amount')
                ->withSum('expenses as expense_total', 'amount')
                ->orderBy('created_at')
                ->get();

            $projectTemplates = \App\Models\DeliverableTemplate::query()
                ->where('tenant_id', $user?->tenant_id)
                ->where('context', 'project')
                ->with('latestPublishedVersion')
                ->get()
                ->filter(fn ($t) => $t->latestPublishedVersion !== null)
                ->values();

            return view('projects.show', [
                'project' => $project,
                'documentChecklist' => $documentChecklist,
                'designItems' => $designItems,
                'blockedItems' => $blockedItems,
                'sectionTasks' => $sectionTasks,
                'contracts' => $contracts,
                'projectTemplates' => $projectTemplates,
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

    public function renderProjectDocument(string $projectId, string $template, DeliverableTemplateVersionService $versionService, DocumentContextRegistry $contextRegistry, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $user = Auth::user();
        $project = AppProject::query()->where('tenant_id', $user?->tenant_id)->findOrFail($projectId);

        $tpl = DeliverableTemplate::query()
            ->where('tenant_id', $user?->tenant_id)
            ->where('context', 'project')
            ->findOrFail($template);

        $version = $tpl->latestPublishedVersion()->first();
        if ($version === null) {
            abort(404);
        }

        $html = (string) Storage::disk('local')->get($version->storage_path);
        $context = $contextRegistry->get('project')->build($project);
        $rendered = $versionService->renderHtml($html, $context);

        try {
            $pdfBytes = $pdfService->render($rendered);
        } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
            return back()->with('error', 'Không thể tạo PDF vào lúc này.');
        }

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . Str::slug($tpl->name) . '-' . ($project->code ?? 'du-an') . '.pdf"',
        ]);
    }
}

<?php declare(strict_types=1);

namespace Src\WorkTemplate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Src\WorkTemplate\Models\Template;
use Src\WorkTemplate\Models\TemplateVersion;
use Src\WorkTemplate\Requests\CreateTemplateRequest;
use Src\WorkTemplate\Requests\UpdateTemplateRequest;
use Src\WorkTemplate\Requests\ApplyTemplateRequest;
use Src\WorkTemplate\Resources\TemplateResource;
use Src\WorkTemplate\Resources\TemplateCollection;
use Src\WorkTemplate\Services\TemplateService;
use Src\Foundation\Utils\JSendResponse;

/**
 * TemplateController
 * 
 * Quản lý các API endpoints cho Template system
 * Bao gồm CRUD operations và apply template logic
 */
class TemplateController extends Controller
{
    /**
     * Template service để xử lý business logic
     */
    protected TemplateService $templateService;

    /**
     * Constructor
     */
    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Lấy ID người dùng hiện tại một cách an toàn
     * 
     * @param Request $request
     * @return int|null
     */
    protected function getUserId(Request $request): ?string
    {
        $user = $request->user('api');
        return $user ? $user->id : null;
    }

    /**
     * Lấy danh sách templates với pagination và filtering
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Template::query()->with(['latestVersion']);
        
        // Filter theo category nếu có
        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }
        
        // Filter theo status nếu có
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        
        // Search theo name nếu có
        if ($request->has('search')) {
            $query->where('template_name', 'like', '%' . $request->get('search') . '%');
        }
        
        // Sắp xếp
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Giới hạn tối đa 100
        $templates = $query->paginate($perPage);
        
        return JSendResponse::success([
            'templates' => $templates
        ]);
    }

    /**
     * Tạo template mới
     * 
     * @param CreateTemplateRequest $request
     * @return JsonResponse
     */
    public function store(CreateTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->templateService->createTemplate(
                $request->validated(),
                $request->user('api')->id  // Sửa từ $request->user()->id
            );
            
            return JSendResponse::success([
                'template' => new TemplateResource($template->load(['latestVersion']))
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to create template: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy chi tiết template theo ID
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $template = Template::with(['versions', 'latestVersion'])->find($id);
        
        if (!$template) {
            return JSendResponse::error('Template không tồn tại', 404);
        }
        
        return JSendResponse::success([
            'template' => new TemplateResource($template)
        ]);
    }

    /**
     * Cập nhật template
     * 
     * @param UpdateTemplateRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateTemplateRequest $request, string $id): JsonResponse
    {
        $template = Template::find($id);
        
        if (!$template) {
            return JSendResponse::error('Template không tồn tại', 404);
        }
        
        try {
            $updatedTemplate = $this->templateService->updateTemplate(
                $template,
                $request->validated(),
                $request->user('api')->id  // Sửa từ $request->user()->id
            );
            
            return JSendResponse::success([
                'template' => new TemplateResource($updatedTemplate->load(['latestVersion']))
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to update template: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Xóa template (soft delete)
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $userId = $this->getUserId($request);
        if (!$userId) {
            return JSendResponse::error('Unauthorized', 401);
        }

        $template = Template::find($id);
        
        if (!$template) {
            return JSendResponse::error('Template không tồn tại', 404);
        }
        
        try {
            $template->update([
                'is_active' => false,
                'updated_by' => $userId
            ]);
            
            $template->delete();
            
            return JSendResponse::success(
                null,
                'Template deleted successfully'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to delete template: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Áp dụng template vào project
     * 
     * @param ApplyTemplateRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function apply(ApplyTemplateRequest $request, string $id): JsonResponse
    {
        // O-17 (latent path, parameterless `rbac` gate only): same task-creation authority as every other creation route.
        $actor = $request->user('api') ?? $request->user();
        $project = \App\Models\Project::query()
            ->where('tenant_id', (string) ($actor?->tenant_id ?? ''))
            ->whereKey((string) $request->get('project_id'))
            ->first();
        $denial = $project === null
            ? \App\Support\TaskCreationAuthority::DENY_TENANT
            : \App\Support\TaskCreationAuthority::denialReason($actor, $project, true);
        if ($denial !== null) {
            \App\Support\TaskCreationAuthority::logDenial($actor, $project, $denial, 'api.templates.apply');

            return JSendResponse::error('Bạn không có quyền thực hiện thao tác này.', 403);
        }

        $template = Template::with(['latestVersion'])->find($id);
        
        if (!$template) {
            return JSendResponse::error('Template không tồn tại', 404);
        }
        
        if (!$template->is_active) {
            return JSendResponse::error('Template is not active', 400);
        }
        
        try {
            // O-17 Gate 6: the created project tasks and their audit rows commit or roll back together.
            $result = \Illuminate\Support\Facades\DB::transaction(function () use ($template, $request, $actor, $project) {
                $existing = \Illuminate\Support\Facades\DB::table('project_tasks')->where('project_id', (string) $project->id)->pluck('id')->all();
                $result = $this->templateService->applyTemplateToProject(
                    $template,
                    $request->get('project_id'),
                    $request->get('mode', 'full'),
                    $request->get('conditional_tags', []),
                    null,
                    null,
                    $request->user('api')->id  // Sửa từ $request->user()->id
                );
                $created = \Illuminate\Support\Facades\DB::table('project_tasks')
                    ->where('project_id', (string) $project->id)
                    ->whereNotIn('id', $existing)
                    ->pluck('id');
                foreach ($created as $taskId) {
                    \App\Support\TaskCreationAuthority::auditIds($actor, (string) $taskId, (string) $project->id, (string) $project->tenant_id, 'api.templates.apply', null, 'project_task');
                }

                return $result;
            });
            
            return JSendResponse::success(
                $result,
                'Template applied successfully'
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to apply template: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy danh sách versions của template
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function versions(string $id): JsonResponse
    {
        $template = Template::find($id);
        
        if (!$template) {
            return JSendResponse::error('Template không tồn tại', 404);
        }
        
        $versions = $template->versions()
            ->orderBy('version', 'desc')
            ->get();
        
        return JSendResponse::success(
            $versions,
            'Template versions retrieved successfully'
        );
    }

    /**
     * Lấy categories có sẵn
     * 
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        $categories = Template::getAvailableCategories();
        
        return JSendResponse::success(
            $categories,
            'Template categories retrieved successfully'
        );
    }
}

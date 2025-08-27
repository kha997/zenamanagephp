<?php declare(strict_types=1);

namespace Src\CoreProject\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\CoreProject\Models\WorkTemplate;
use Src\CoreProject\Resources\WorkTemplateResource;
use Src\CoreProject\Requests\StoreWorkTemplateRequest;
use Src\CoreProject\Requests\UpdateWorkTemplateRequest;
use Src\RBAC\Middleware\RBACMiddleware;
use Src\Foundation\Utils\JSendResponse;

/**
 * Controller xử lý các hoạt động CRUD cho WorkTemplate
 * 
 * @package Src\CoreProject\Controllers
 */
class WorkTemplateController
{
    /**
     * Constructor - áp dụng RBAC middleware
     */
    public function __construct()
    {
        $this->middleware(RBACMiddleware::class);
    }

    /**
     * Lấy danh sách work templates
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = WorkTemplate::query();

            // Filter theo category
            if ($request->has('category')) {
                $query->where('category', $request->get('category'));
            }

            // Search theo tên
            if ($request->has('search')) {
                $query->where('name', 'LIKE', '%' . $request->get('search') . '%');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            $templates = $query->paginate(
                $request->get('per_page', 15)
            );

            return JSendResponse::success([
                'templates' => WorkTemplateResource::collection($templates->items()),
                'pagination' => [
                    'current_page' => $templates->currentPage(),
                    'last_page' => $templates->lastPage(),
                    'per_page' => $templates->perPage(),
                    'total' => $templates->total()
                ]
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách templates: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo work template mới
     *
     * @param StoreWorkTemplateRequest $request
     * @return JsonResponse
     */
    public function store(StoreWorkTemplateRequest $request): JsonResponse
    {
        try {
            $templateData = $request->validated();
            
            // Convert template_data array to JSON
            if (isset($templateData['template_data'])) {
                $templateData['template_data'] = json_encode($templateData['template_data']);
            }

            $template = WorkTemplate::create($templateData);

            // Dispatch event
            event(new \Src\CoreProject\Events\WorkTemplateCreated($template));

            return JSendResponse::success([
                'template' => new WorkTemplateResource($template),
                'message' => 'Work template đã được tạo thành công.'
            ], 201);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể tạo work template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy thông tin chi tiết một work template
     *
     * @param int $templateId
     * @return JsonResponse
     */
    public function show(int $templateId): JsonResponse
    {
        try {
            $template = WorkTemplate::findOrFail($templateId);

            return JSendResponse::success([
                'template' => new WorkTemplateResource($template)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Work template không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy thông tin work template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Cập nhật thông tin work template
     *
     * @param UpdateWorkTemplateRequest $request
     * @param int $templateId
     * @return JsonResponse
     */
    public function update(UpdateWorkTemplateRequest $request, int $templateId): JsonResponse
    {
        try {
            $template = WorkTemplate::findOrFail($templateId);

            $templateData = $request->validated();
            
            // Convert template_data array to JSON
            if (isset($templateData['template_data'])) {
                $templateData['template_data'] = json_encode($templateData['template_data']);
            }

            $template->update($templateData);

            // Dispatch event
            event(new \Src\CoreProject\Events\WorkTemplateUpdated($template));

            return JSendResponse::success([
                'template' => new WorkTemplateResource($template),
                'message' => 'Work template đã được cập nhật thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Work template không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể cập nhật work template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Xóa work template
     *
     * @param int $templateId
     * @return JsonResponse
     */
    public function destroy(int $templateId): JsonResponse
    {
        try {
            $template = WorkTemplate::findOrFail($templateId);
            $template->delete();

            // Dispatch event
            event(new \Src\CoreProject\Events\WorkTemplateDeleted($template));

            return JSendResponse::success([
                'message' => 'Work template đã được xóa thành công.'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Work template không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể xóa work template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Tạo bản sao của work template với version mới
     *
     * @param int $templateId
     * @return JsonResponse
     */
    public function duplicate(int $templateId): JsonResponse
    {
        try {
            $originalTemplate = WorkTemplate::findOrFail($templateId);
            
            $newTemplate = $originalTemplate->replicate();
            $newTemplate->name = $originalTemplate->name . ' (Copy)';
            $newTemplate->version = $originalTemplate->version + 1;
            $newTemplate->save();

            return JSendResponse::success([
                'template' => new WorkTemplateResource($newTemplate),
                'message' => 'Work template đã được sao chép thành công.'
            ], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Work template không tồn tại.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể sao chép work template: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Lấy danh sách categories có sẵn
     *
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        try {
            $categories = [
                'design' => 'Thiết kế',
                'construction' => 'Thi công',
                'qc' => 'Kiểm soát chất lượng',
                'inspection' => 'Nghiệm thu'
            ];

            return JSendResponse::success([
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Áp dụng work template vào project
     *
     * @param Request $request
     * @param int $templateId
     * @return JsonResponse
     */
    public function applyToProject(Request $request, int $templateId): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|exists:projects,id',
                'component_id' => 'nullable|exists:components,id',
                'default_assignee_id' => 'nullable|exists:users,id',
                'base_start_date' => 'nullable|date',
                'preview_only' => 'nullable|boolean'
            ]);
            
            $template = WorkTemplate::findOrFail($templateId);
            $project = \Src\CoreProject\Models\Project::findOrFail($request->project_id);
            $component = $request->component_id ? 
                \Src\CoreProject\Models\Component::findOrFail($request->component_id) : null;
            
            $applicationService = app(\Src\CoreProject\Services\WorkTemplateApplicationService::class);
            
            // Nếu chỉ preview
            if ($request->boolean('preview_only')) {
                $preview = $applicationService->previewTemplateApplication(
                    $template,
                    $project,
                    $component
                );
                
                return JSendResponse::success([
                    'preview' => $preview,
                    'message' => 'Preview áp dụng template thành công.'
                ]);
            }
            
            // Áp dụng thực tế
            $options = [];
            if ($request->default_assignee_id) {
                $options['default_assignee_id'] = $request->default_assignee_id;
            }
            if ($request->base_start_date) {
                $options['base_start_date'] = $request->base_start_date;
            }
            
            $result = $applicationService->applyTemplateToProject(
                $template,
                $project,
                $component,
                $options
            );
            
            return JSendResponse::success([
                'result' => $result,
                'message' => 'Áp dụng work template thành công.'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return JSendResponse::error('Dữ liệu không hợp lệ: ' . implode(', ', $e->validator->errors()->all()), 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return JSendResponse::error('Không tìm thấy resource.', 404);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể áp dụng work template: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Lấy danh sách conditional tags có sẵn
     *
     * @return JsonResponse
     */
    public function conditionalTags(): JsonResponse
    {
        try {
            $conditionalTagService = app(\Src\CoreProject\Services\ConditionalTagService::class);
            $tags = $conditionalTagService->getPredefinedTags();
            
            return JSendResponse::success([
                'conditional_tags' => $tags
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error('Không thể lấy danh sách conditional tags: ' . $e->getMessage(), 500);
        }
    }
}
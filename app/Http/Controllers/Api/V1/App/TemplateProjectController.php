<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\Api\V1\App\ProjectFromTemplateRequest;
use App\Services\ProjectManagementService;
use App\Services\TemplateManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for creating projects from templates
 */
class TemplateProjectController extends BaseApiV1Controller
{
    public function __construct(
        private TemplateManagementService $templateService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Create a project from a template
     * 
     * @param ProjectFromTemplateRequest $request
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function store(ProjectFromTemplateRequest $request, string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Get template and verify it belongs to the tenant
            $template = $this->templateService->getTemplateById($tenantId, $tpl);
            
            if (!$template) {
                return $this->errorResponse('Template not found', 404, null, 'TEMPLATE_NOT_FOUND');
            }
            
            // Verify template is for project type
            if ($template->category !== 'project') {
                return $this->errorResponse(
                    'Template is not a project template',
                    422,
                    null,
                    'INVALID_TEMPLATE_TYPE'
                );
            }
            
            // Create project from template
            $project = $this->projectService->createProjectFromTemplate(
                $tenantId,
                $template,
                $request->validated()
            );
            
            return $this->successResponse(
                $project,
                'Project created from template successfully',
                201
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404/422 from service)
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'template_id' => $tpl]);
            return $this->errorResponse(
                $e->getMessage() ?: 'An error occurred while creating the project',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }
}


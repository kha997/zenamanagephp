<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\Foundation\Helpers\AuthHelper;

use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\WorkTemplate;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Src\Foundation\Events\EventBus;

/**
 * Service xử lý việc áp dụng Work Templates vào Projects
 * 
 * Chịu trách nhiệm:
 * - Tạo tasks từ template data
 * - Xử lý conditional tags và task visibility
 * - Quản lý task dependencies
 * - Validation template data
 * - Event dispatching
 */
class WorkTemplateApplicationService
{
    private ConditionalTagService $conditionalTagService;
    private TaskService $taskService;
    
    public function __construct(
        ConditionalTagService $conditionalTagService,
        TaskService $taskService
    ) {
        $this->conditionalTagService = $conditionalTagService;
        $this->taskService = $taskService;
    }
    
    /**
     * Resolve actor ID từ Auth facade với fallback an toàn
     * 
     * @return string|int
     */
    private function resolveActorId()
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
            return 'system';
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from Auth facade', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }
    
    /**
     * Áp dụng work template vào project
     * 
     * @param WorkTemplate $template Template cần áp dụng
     * @param Project $project Project đích
     * @param Component|null $component Component đích (optional)
     * @param array $options Tùy chọn bổ sung
     * @return array Kết quả áp dụng template
     * @throws ValidationException
     */
    public function applyTemplateToProject(
        WorkTemplate $template,
        Project $project,
        ?Component $component = null,
        array $options = []
    ): array {
        // Validate template trước khi áp dụng
        $this->validateTemplate($template);
        
        // Validate project có thể nhận template
        $this->validateProjectCompatibility($template, $project);
        
        return DB::transaction(function () use ($template, $project, $component, $options) {
            $result = [
                'template_id' => $template->ulid,
                'project_id' => $project->ulid,
                'component_id' => $component?->ulid,
                'tasks_created' => [],
                'tasks_skipped' => [],
                'warnings' => [],
                'summary' => []
            ];
            
            // Lấy active tags cho project
            $activeTags = $this->conditionalTagService->getActiveTagsForProject($project->id);
            
            // Tạo mapping cho task dependencies
            $taskMapping = [];
            
            // Phase 1: Tạo tất cả tasks (bao gồm cả hidden)
            foreach ($template->template_data['tasks'] ?? [] as $taskData) {
                try {
                    $task = $this->createTaskFromTemplate(
                        $taskData,
                        $project,
                        $component,
                        $activeTags,
                        $options
                    );
                    
                    $result['tasks_created'][] = [
                        'template_task_id' => $taskData['id'],
                        'task_id' => $task->ulid,
                        'name' => $task->name,
                        'is_hidden' => $task->is_hidden,
                        'conditional_tag' => $task->conditional_tag
                    ];
                    
                    $taskMapping[$taskData['id']] = $task;
                    
                } catch (\Exception $e) {
                    Log::warning("Failed to create task from template", [
                        'template_id' => $template->ulid,
                        'task_data' => $taskData,
                        'error' => $e->getMessage()
                    ]);
                    
                    $result['tasks_skipped'][] = [
                        'template_task_id' => $taskData['id'],
                        'name' => $taskData['name'] ?? 'Unknown',
                        'reason' => $e->getMessage()
                    ];
                }
            }
            
            // Phase 2: Cập nhật dependencies với actual task ULIDs
            $this->updateTaskDependencies($template->template_data['tasks'] ?? [], $taskMapping, $result);
            
            // Phase 3: Tạo task assignments nếu có trong template
            $this->createTaskAssignments($template->template_data['tasks'] ?? [], $taskMapping, $options, $result);
            
            // Tạo summary
            $result['summary'] = [
                'total_template_tasks' => count($template->template_data['tasks'] ?? []),
                'tasks_created' => count($result['tasks_created']),
                'tasks_skipped' => count($result['tasks_skipped']),
                'visible_tasks' => count(array_filter($result['tasks_created'], fn($t) => !$t['is_hidden'])),
                'hidden_tasks' => count(array_filter($result['tasks_created'], fn($t) => $t['is_hidden']))
            ];
            
            // Dispatch event
            // Trong phương thức applyTemplateToProject, tại dòng 134:
            EventBus::dispatch('WorkTemplate.Applied', [
                'template_id' => $template->ulid,
                'project_id' => $project->ulid,
                'component_id' => $component?->ulid,
                'result' => $result,
                'actor_id' => $this->resolveActorId()
            ]);
            
            Log::info("Work template applied successfully", [
                'template_id' => $template->ulid,
                'project_id' => $project->ulid,
                'summary' => $result['summary']
            ]);
            
            return $result;
        });
    }
    
    /**
     * Tạo task từ template data
     * 
     * @param array $taskData Dữ liệu task từ template
     * @param Project $project Project đích
     * @param Component|null $component Component đích
     * @param array $activeTags Danh sách active tags
     * @param array $options Tùy chọn bổ sung
     * @return Task Task đã tạo
     */
    private function createTaskFromTemplate(
        array $taskData,
        Project $project,
        ?Component $component,
        array $activeTags,
        array $options
    ): Task {
        // Kiểm tra conditional tag
        $isHidden = false;
        if (!empty($taskData['conditional_tag'])) {
            $isHidden = !in_array($taskData['conditional_tag'], $activeTags);
        }
        
        // Chuẩn bị dữ liệu task
        $taskCreateData = [
            'project_id' => $project->id,
            'component_id' => $component?->id,
            'name' => $taskData['name'],
            'description' => $taskData['description'] ?? null,
            'estimated_hours' => $taskData['estimated_hours'] ?? 0.0,
            'priority' => $taskData['priority'] ?? Task::PRIORITY_MEDIUM, // Thay từ PRIORITY_NORMAL
            'conditional_tag' => $taskData['conditional_tag'] ?? null,
            'is_hidden' => $isHidden,
            'tags' => $taskData['tags'] ?? [],
            'status' => Task::STATUS_PENDING
        ];
        
        // Áp dụng date offsets nếu có
        if (isset($taskData['start_date_offset']) && isset($options['base_start_date'])) {
            $baseDate = \Carbon\Carbon::parse($options['base_start_date']);
            $taskCreateData['start_date'] = $baseDate->addDays($taskData['start_date_offset'])->toDateString();
        }
        
        if (isset($taskData['duration_days']) && isset($taskCreateData['start_date'])) {
            $startDate = \Carbon\Carbon::parse($taskCreateData['start_date']);
            $taskCreateData['end_date'] = $startDate->addDays($taskData['duration_days'])->toDateString();
        }
        
        return Task::create($taskCreateData);
    }
    
    /**
     * Cập nhật dependencies cho các tasks đã tạo
     * 
     * @param array $templateTasks Dữ liệu tasks từ template
     * @param array $taskMapping Mapping từ template task ID sang actual Task
     * @param array &$result Kết quả để cập nhật warnings
     */
    private function updateTaskDependencies(array $templateTasks, array $taskMapping, array &$result): void
    {
        foreach ($templateTasks as $taskData) {
            if (empty($taskData['dependencies']) || !isset($taskMapping[$taskData['id']])) {
                continue;
            }
            
            $task = $taskMapping[$taskData['id']];
            $actualDependencies = [];
            
            foreach ($taskData['dependencies'] as $depId) {
                if (isset($taskMapping[$depId])) {
                    $actualDependencies[] = $taskMapping[$depId]->ulid;
                } else {
                    $result['warnings'][] = [
                        'type' => 'missing_dependency',
                        'task_id' => $task->ulid,
                        'task_name' => $task->name,
                        'missing_dependency_id' => $depId,
                        'message' => "Dependency task '{$depId}' not found for task '{$task->name}'"
                    ];
                }
            }
            
            if (!empty($actualDependencies)) {
                $task->update(['dependencies_json' => $actualDependencies]);
            }
        }
    }
    
    /**
     * Tạo task assignments từ template
     * 
     * @param array $templateTasks Dữ liệu tasks từ template
     * @param array $taskMapping Mapping từ template task ID sang actual Task
     * @param array $options Tùy chọn bổ sung
     * @param array &$result Kết quả để cập nhật
     */
    private function createTaskAssignments(array $templateTasks, array $taskMapping, array $options, array &$result): void
    {
        if (!isset($options['default_assignee_id'])) {
            return;
        }
        
        $defaultAssigneeId = $options['default_assignee_id'];
        
        foreach ($templateTasks as $taskData) {
            if (!isset($taskMapping[$taskData['id']])) {
                continue;
            }
            
            $task = $taskMapping[$taskData['id']];
            
            // Tạo assignment với 100% cho default assignee
            TaskAssignment::create([
                'task_id' => $task->id,
                'user_id' => $defaultAssigneeId,
                'split_percent' => 100.0
            ]);
        }
    }
    
    /**
     * Validate template trước khi áp dụng
     * 
     * @param WorkTemplate $template
     * @throws ValidationException
     */
    private function validateTemplate(WorkTemplate $template): void
    {
        if (!$template->is_active) {
            throw ValidationException::withMessages([
                'template' => 'Template không active, không thể áp dụng.'
            ]);
        }
        
        if (!$template->validateTemplateData()) {
            throw ValidationException::withMessages([
                'template_data' => 'Dữ liệu template không hợp lệ.'
            ]);
        }
        
        // Validate task structure
        $tasks = $template->template_data['tasks'] ?? [];
        if (empty($tasks)) {
            throw ValidationException::withMessages([
                'template_data' => 'Template phải có ít nhất một task.'
            ]);
        }
        
        // Validate task dependencies
        $taskIds = array_column($tasks, 'id');
        foreach ($tasks as $task) {
            if (!empty($task['dependencies'])) {
                foreach ($task['dependencies'] as $depId) {
                    if (!in_array($depId, $taskIds)) {
                        throw ValidationException::withMessages([
                            'template_data' => "Task '{$task['name']}' có dependency không tồn tại: {$depId}"
                        ]);
                    }
                }
            }
        }
    }
    
    /**
     * Validate project có thể nhận template
     * 
     * @param WorkTemplate $template
     * @param Project $project
     * @throws ValidationException
     */
    private function validateProjectCompatibility(WorkTemplate $template, Project $project): void
    {
        // Kiểm tra project status
        if (in_array($project->status, ['completed', 'cancelled'])) {
            throw ValidationException::withMessages([
                'project' => 'Không thể áp dụng template cho project đã hoàn thành hoặc bị hủy.'
            ]);
        }
        
        // Có thể thêm các validation khác như:
        // - Kiểm tra category compatibility
        // - Kiểm tra project phase
        // - Kiểm tra permissions
    }
    
    /**
     * Lấy preview của việc áp dụng template (không tạo thực tế)
     * 
     * @param WorkTemplate $template
     * @param Project $project
     * @param Component|null $component
     * @return array Preview data
     */
    public function previewTemplateApplication(
        WorkTemplate $template,
        Project $project,
        ?Component $component = null
    ): array {
        $this->validateTemplate($template);
        
        $activeTags = $this->conditionalTagService->getActiveTagsForProject($project->id);
        $tasks = $template->template_data['tasks'] ?? [];
        
        $preview = [
            'template' => [
                'id' => $template->ulid,
                'name' => $template->name,
                'category' => $template->category,
                'version' => $template->version
            ],
            'target' => [
                'project_id' => $project->ulid,
                'project_name' => $project->name,
                'component_id' => $component?->ulid,
                'component_name' => $component?->name
            ],
            'active_tags' => $activeTags,
            'tasks_preview' => [],
            'summary' => []
        ];
        
        $visibleCount = 0;
        $hiddenCount = 0;
        
        foreach ($tasks as $taskData) {
            $isHidden = false;
            if (!empty($taskData['conditional_tag'])) {
                $isHidden = !in_array($taskData['conditional_tag'], $activeTags);
            }
            
            if ($isHidden) {
                $hiddenCount++;
            } else {
                $visibleCount++;
            }
            
            $preview['tasks_preview'][] = [
                'id' => $taskData['id'],
                'name' => $taskData['name'],
                'description' => $taskData['description'] ?? null,
                'estimated_hours' => $taskData['estimated_hours'] ?? 0,
                'priority' => $taskData['priority'] ?? 'normal',
                'conditional_tag' => $taskData['conditional_tag'] ?? null,
                'will_be_hidden' => $isHidden,
                'dependencies' => $taskData['dependencies'] ?? [],
                'tags' => $taskData['tags'] ?? []
            ];
        }
        
        $preview['summary'] = [
            'total_tasks' => count($tasks),
            'visible_tasks' => $visibleCount,
            'hidden_tasks' => $hiddenCount,
            'has_dependencies' => !empty(array_filter($tasks, fn($t) => !empty($t['dependencies'])))
        ];
        
        return $preview;
    }
}

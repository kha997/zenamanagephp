<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Models\Project;
use App\Models\User;
use App\Support\TaskCreationAuthority;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Src\CoreProject\Models\Task;
use Src\Shared\Requests\BaseApiRequest;

class StoreTaskRequest extends BaseApiRequest
{
    private const VALID_ASSIGNMENT_ROLES = [
        'assignee',
        'reviewer',
        'watcher'
    ];

    /**
     * O-17 Option B (Owner-approved): decided here, BEFORE any field is validated, and independent of the route middleware
     * (CB-4: a role named `super_admin` passes `rbac:task.create` without the permission).
     *   - no canonical task.create            -> 403
     *   - project not in the caller's tenant  -> left to the tenant-scoped `project_id` rule (422, unchanged)
     *   - not the project's PM / participant  -> 403
     */
    public function authorize(): bool
    {
        $user = $this->user('api') ?? $this->user();
        if (!$user instanceof User || !$user->hasPermission(TaskCreationAuthority::PERMISSION)) {
            TaskCreationAuthority::logDenial($user, null, TaskCreationAuthority::DENY_PERMISSION, 'api.v1.tasks.store');

            return false;
        }

        $project = $this->authorisedProjectCandidate();
        if ($project === null) {
            return true;
        }

        $denial = TaskCreationAuthority::denialReason($user, $project);
        if ($denial !== null) {
            TaskCreationAuthority::logDenial($user, $project, $denial, 'api.v1.tasks.store');

            return false;
        }

        return true;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'Bạn không có quyền thực hiện thao tác này.',
        ], 403));
    }

    /** The project, looked up ONLY inside the caller's tenant. */
    private function authorisedProjectCandidate(): ?Project
    {
        $user = $this->user('api') ?? $this->user();
        $projectId = $this->resolvedProjectId();
        if (!$user instanceof User || !is_string($projectId) || $projectId === '') {
            return null;
        }

        return Project::query()->where('tenant_id', (string) $user->tenant_id)->whereKey($projectId)->first();
    }

    /**
     * Validation rules cho việc tạo task mới
     */
    public function rules(): array
    {
        $projectId = $this->resolvedProjectId();
        $project = $this->authorisedProjectCandidate();
        $eligibleUser = function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
            if ($project === null || !TaskCreationAuthority::eligibleAssignee($project, $value)) {
                $fail("The {$attribute} must be the project manager or an active participant of this project.");
            }
        };
        $taskOfProject = function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
            if ($project === null || !TaskCreationAuthority::taskOfProject($project, $value)) {
                $fail("The {$attribute} must be a task of the same project.");
            }
        };
        $nameRules = [
            'required',
            'string',
            'max:255'
        ];

        if ($projectId) {
            $nameRules[] = Rule::unique('tasks')->where('project_id', $projectId);
        }

        return [
            // UAT-RECOVERY-04c: the project must belong to the caller's tenant (a global `exists` let a task be attached to another tenant's project).
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where(fn ($q) => $q->where('tenant_id', ($this->user('api') ?? $this->user())?->tenant_id))
            ],
            'tenant_id' => [
                'prohibited'
            ],
            // O-17 Gate 5: references must belong to this project (were global `exists`).
            'component_id' => [
                'nullable',
                'string',
                Rule::exists('components', 'id')->where('project_id', (string) $projectId)
            ],
            'phase_id' => [
                'nullable',
                'string',
                Rule::exists('project_phases', 'id')->where('project_id', (string) $projectId)
            ],
            'name' => $nameRules,
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'start_date' => [
                'nullable',
                'date'
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date'
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in(Task::VALID_STATUSES)
            ],
            'priority' => [
                'nullable',
                'string',
                Rule::in(Task::VALID_PRIORITIES)
            ],
            'dependencies' => [
                'nullable',
                'array'
            ],
            'dependencies.*' => [
                'string',
                'max:36',
                $taskOfProject
            ],
            'conditional_tag' => [
                'nullable',
                'string',
                'max:100'
            ],
            'is_hidden' => [
                'nullable',
                'boolean'
            ],
            'estimated_hours' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.99'
            ],
            'actual_hours' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999.99'
            ],
            'progress_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'tags' => [
                'nullable',
                'array'
            ],
            'tags.*' => [
                'string',
                'max:50'
            ],
            'visibility' => [
                'nullable',
                'string',
                'in:internal,client'
            ],
            'client_approved' => [
                'nullable',
                'boolean'
            ],
            'assignments' => [
                'nullable',
                'array'
            ],
            'assignments.*.user_id' => [
                'required_with:assignments',
                'string',
                $eligibleUser
            ],
            'assignments.*.split_percent' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:100'
            ],
            'assignments.*.role' => [
                'nullable',
                'string',
                Rule::in(self::VALID_ASSIGNMENT_ROLES)
            ],
        ];
    }

    /**
     * Điều chỉnh dữ liệu mặc định trước validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->resolvedProjectId(),
            'status' => $this->status ?? Task::STATUS_PENDING,
            'priority' => $this->priority ?? Task::PRIORITY_MEDIUM,
            'visibility' => $this->visibility ?? 'internal',
            'client_approved' => $this->client_approved ?? false,
            'estimated_hours' => $this->estimated_hours ?? 0.0,
            'actual_hours' => $this->actual_hours ?? 0.0,
            'progress_percent' => $this->progress_percent ?? 0.0,
        ]);
    }

    private function resolvedProjectId(): ?string
    {
        return $this->input('project_id')
            ?? $this->route('projectId')
            ?? $this->route('project');
    }
}

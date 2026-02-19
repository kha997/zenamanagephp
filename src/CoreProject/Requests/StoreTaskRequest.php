<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

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
     * Authorization được xử lý bởi RBAC middleware
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules cho việc tạo task mới
     */
    public function rules(): array
    {
        $projectId = $this->resolvedProjectId();
        $nameRules = [
            'required',
            'string',
            'max:255'
        ];

        if ($projectId) {
            $nameRules[] = Rule::unique('tasks')->where('project_id', $projectId);
        }

        return [
            'project_id' => [
                'required',
                'string',
                'exists:projects,id'
            ],
            'tenant_id' => [
                'prohibited'
            ],
            'component_id' => [
                'nullable',
                'string',
                'exists:components,id'
            ],
            'phase_id' => [
                'nullable',
                'string',
                'exists:project_phases,id'
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
                'exists:tasks,id'
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
                'exists:users,id'
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

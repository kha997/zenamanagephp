<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Src\CoreProject\Models\Project;

/**
 * Form Request để xác thực dữ liệu khi cập nhật Project
 * 
 * @package Src\CoreProject\Requests
 */
class UpdateProjectRequest extends BaseApiRequest
{
    /**
     * Các quy tắc validation cho request
     */
    public function rules(): array
    {
        $projectId = $this->resolveProjectId();
        $project = $this->resolveCoreProject();
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('projects')
                    ->ignore($projectId)
                    ->where('tenant_id', $project ? $project->tenant_id : ($this->user()->tenant_id ?? 1))
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'start_date' => [
                'sometimes',
                'required',
                'date'
            ],
            'end_date' => [
                'sometimes',
                'required',
                'date',
                'after:start_date'
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', array_keys(Project::STATUSES))
            ],
            'progress' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'planned_cost' => [
                'nullable',
                'numeric',
                'min:0'
            ],
            'actual_cost' => [
                'nullable',
                'numeric',
                'min:0'
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
            ]
        ];
    }

    /**
     * Validation sau khi rules cơ bản đã pass
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $this->validateStatusTransition($validator);
            $this->validateProgressConsistency($validator);
        });
    }

    /**
     * Kiểm tra tính hợp lệ của việc chuyển đổi trạng thái
     */
    private function validateStatusTransition($validator): void
    {
        $project = $this->resolveCoreProject();
        $newStatus = $this->input('status');
        
        if (!$project || !$newStatus || $project->status === $newStatus) {
            return;
        }

        // Cho phép kích hoạt lại dự án nếu nó đã hoàn thành hoặc hủy
        if (in_array($project->status, ['completed', 'cancelled'], true) && $newStatus === 'active') {
            return;
        }

        // Định nghĩa các chuyển đổi trạng thái hợp lệ
        $validTransitions = [
            'planning' => ['active', 'cancelled'],
            'active' => ['on_hold', 'completed', 'cancelled'],
            'on_hold' => ['active', 'cancelled'],
            'completed' => [], // Không thể chuyển từ completed
            'cancelled' => [] // Không thể chuyển từ cancelled
        ];

        if (!in_array($newStatus, $validTransitions[$project->status] ?? [])) {
            $validator->errors()->add('status', 
                "Không thể chuyển từ trạng thái '{$project->status}' sang '{$newStatus}'."
            );
        }
    }

    /**
     * Kiểm tra tính nhất quán của progress
     */
    private function validateProgressConsistency($validator): void
    {
        $progress = $this->input('progress');
        $status = $this->input('status');
        $project = $this->resolveCoreProject();
        
        if (!$project) {
            return;
        }

        $finalProgress = $progress ?? $project->progress;
        $finalStatus = $status ?? $project->status;

        // Kiểm tra logic nghiệp vụ
        if ($finalStatus === 'completed' && $finalProgress < 100) {
            $validator->errors()->add('progress', 'Dự án hoàn thành phải có tiến độ 100%.');
        }
        
        if ($finalStatus === 'planning' && $finalProgress > 0) {
            $validator->errors()->add('progress', 'Dự án đang lập kế hoạch không thể có tiến độ > 0%.');
        }
        
        if ($finalProgress == 100 && !in_array($finalStatus, ['completed', 'cancelled'])) {
            $validator->errors()->add('status', 'Dự án có tiến độ 100% phải ở trạng thái hoàn thành.');
        }
    }

    private function resolveProjectId(): ?string
    {
        $projectParam = $this->route('project');

        if ($projectParam instanceof Model) {
            return (string) $projectParam->getKey();
        }

        return $projectParam;
    }

    private function resolveCoreProject(): ?Project
    {
        $projectId = $this->resolveProjectId();

        return $projectId ? Project::find($projectId) : null;
    }
}

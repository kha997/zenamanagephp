<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * ApplyTemplateRequest
 * 
 * Validation cho việc áp dụng template vào project
 * Kiểm tra project tồn tại và các options hợp lệ
 */
class ApplyTemplateRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        // Kiểm tra quyền apply template và quyền trên project
        $projectId = $this->input('project_id');
        
        return $this->user()->can('template.apply') && 
               $this->user()->can('project.update', $projectId);
    }

    /**
     * Định nghĩa validation rules
     */
    public function rules(): array
    {
        return [
            // Project info
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where(function ($query) {
                    // Chỉ cho phép apply vào projects mà user có quyền
                    $query->where('tenant_id', $this->getTenantId());
                })
            ],
            
            // Apply options
            'mode' => [
                'sometimes',
                'string',
                Rule::in(['full', 'partial']) // full: thay thế hoàn toàn, partial: merge
            ],
            'preserve_existing' => [
                'sometimes',
                'boolean' // Có giữ lại tasks/phases hiện tại không
            ],
            'start_date_offset' => [
                'sometimes',
                'integer',
                'min:0' // Số ngày offset từ ngày hiện tại
            ],
            
            // Conditional tags
            'conditional_tags' => [
                'sometimes',
                'array'
            ],
            'conditional_tags.*' => [
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9_-]+$/'
            ],
            
            // Partial sync options
            'selected_phases' => [
                'sometimes',
                'array',
                'required_if:mode,partial' // Bắt buộc khi mode = partial
            ],
            'selected_phases.*' => [
                'string' // Phase IDs từ template
            ],
            'selected_tasks' => [
                'sometimes',
                'array'
            ],
            'selected_tasks.*' => [
                'string' // Task IDs từ template
            ],
            
            // Mapping options
            'phase_mapping' => [
                'sometimes',
                'array' // Map template phases to existing project phases
            ],
            'phase_mapping.*.template_phase_id' => [
                'required_with:phase_mapping',
                'string'
            ],
            'phase_mapping.*.project_phase_id' => [
                'required_with:phase_mapping',
                'string',
                Rule::exists('project_phases', 'id')
            ],
            
            // Notification options
            'notify_assignees' => [
                'sometimes',
                'boolean'
            ],
            'notification_message' => [
                'sometimes',
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'ID dự án là bắt buộc.',
            'project_id.exists' => 'Dự án không tồn tại hoặc bạn không có quyền truy cập.',
            'mode.in' => 'Chế độ áp dụng không hợp lệ.',
            'selected_phases.required_if' => 'Phải chọn ít nhất một giai đoạn khi sử dụng chế độ partial.',
            'conditional_tags.*.regex' => 'Thẻ điều kiện chỉ được chứa chữ cái, số, gạch dưới và gạch ngang.',
            'phase_mapping.*.template_phase_id.required_with' => 'ID giai đoạn template là bắt buộc.',
            'phase_mapping.*.project_phase_id.required_with' => 'ID giai đoạn dự án là bắt buộc.',
            'phase_mapping.*.project_phase_id.exists' => 'Giai đoạn dự án không tồn tại.'
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Đặt các giá trị mặc định
        $defaults = [
            'mode' => 'full',
            'preserve_existing' => false,
            'start_date_offset' => 0,
            'notify_assignees' => true
        ];
        
        foreach ($defaults as $key => $value) {
            if (!$this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
        
        // Chuẩn hóa conditional_tags về lowercase
        if ($this->has('conditional_tags')) {
            $tags = array_map('strtolower', array_map('trim', $this->input('conditional_tags')));
            $this->merge(['conditional_tags' => array_unique($tags)]);
        }
    }

    /**
     * Custom validation logic
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate project không có template đang được apply
            $this->validateProjectNotBusy($validator);
            
            // Validate selected phases/tasks tồn tại trong template
            $this->validateSelectedItems($validator);
            
            // Validate phase mapping hợp lệ
            $this->validatePhaseMapping($validator);
        });
    }

    /**
     * Validate project is not currently being processed
     */
    private function validateProjectNotBusy($validator): void
    {
        $projectId = $this->input('project_id');
        
        // Kiểm tra xem project có đang trong quá trình apply template khác không
        // (có thể implement thông qua cache hoặc database flag)
        $cacheKey = "template_applying_{$projectId}";
        
        if (cache()->has($cacheKey)) {
            $validator->errors()->add(
                'project_id',
                'Dự án đang trong quá trình áp dụng template khác. Vui lòng thử lại sau.'
            );
        }
    }

    /**
     * Validate selected phases and tasks exist in template
     */
    private function validateSelectedItems($validator): void
    {
        $templateId = $this->route('templateId');
        
        // Lấy template để validate
        $template = \Src\WorkTemplate\Models\Template::with('latestVersion')->find($templateId);
        
        if (!$template || !$template->latestVersion) {
            return; // Template validation sẽ được handle ở controller
        }
        
        $templateData = $template->latestVersion->template_data;
        $templatePhaseIds = array_column($templateData['phases'] ?? [], 'id');
        $templateTaskIds = [];
        
        foreach ($templateData['phases'] ?? [] as $phase) {
            foreach ($phase['tasks'] ?? [] as $task) {
                $templateTaskIds[] = $task['id'];
            }
        }
        
        // Validate selected phases
        if ($this->has('selected_phases')) {
            foreach ($this->input('selected_phases') as $phaseId) {
                if (!in_array($phaseId, $templatePhaseIds)) {
                    $validator->errors()->add(
                        'selected_phases',
                        "Giai đoạn {$phaseId} không tồn tại trong template."
                    );
                }
            }
        }
        
        // Validate selected tasks
        if ($this->has('selected_tasks')) {
            foreach ($this->input('selected_tasks') as $taskId) {
                if (!in_array($taskId, $templateTaskIds)) {
                    $validator->errors()->add(
                        'selected_tasks',
                        "Công việc {$taskId} không tồn tại trong template."
                    );
                }
            }
        }
    }

    /**
     * Validate phase mapping is correct
     */
    private function validatePhaseMapping($validator): void
    {
        if (!$this->has('phase_mapping')) {
            return;
        }
        
        $projectId = $this->input('project_id');
        $mapping = $this->input('phase_mapping');
        
        // Lấy danh sách phases của project
        $projectPhaseIds = \Src\CoreProject\Models\ProjectPhase::where('project_id', $projectId)
            ->pluck('id')
            ->toArray();
        
        foreach ($mapping as $index => $map) {
            if (!in_array($map['project_phase_id'], $projectPhaseIds)) {
                $validator->errors()->add(
                    "phase_mapping.{$index}.project_phase_id",
                    'Giai đoạn dự án không thuộc về dự án được chọn.'
                );
            }
        }
        
        // Validate không có duplicate mapping
        $templatePhaseIds = array_column($mapping, 'template_phase_id');
        $projectPhaseIds = array_column($mapping, 'project_phase_id');
        
        if (count($templatePhaseIds) !== count(array_unique($templatePhaseIds))) {
            $validator->errors()->add(
                'phase_mapping',
                'Không được map một giai đoạn template tới nhiều giai đoạn dự án.'
            );
        }
        
        if (count($projectPhaseIds) !== count(array_unique($projectPhaseIds))) {
            $validator->errors()->add(
                'phase_mapping',
                'Không được map nhiều giai đoạn template tới một giai đoạn dự án.'
            );
        }
    }

    /**
     * Lấy tenant_id một cách an toàn
     *
     * @return string|null
     */
    private function getTenantId(): ?string
    {
        try {
            $user = $this->user();
            if ($user && isset($user->tenant_id)) {
                return $user->tenant_id;
            }
            
            Log::warning('ApplyTemplateRequest: Không thể lấy tenant_id từ user', [
                'user_id' => $user?->id ?? 'null',
                'request_path' => $this->path()
            ]);
            
            return null;
        } catch (\Exception $e) {
            Log::error('ApplyTemplateRequest: Lỗi khi lấy tenant_id', [
                'error' => $e->getMessage(),
                'request_path' => $this->path()
            ]);
            
            return null;
        }
    }
}
<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho Task validation
 * 
 * @package App\Http\Requests
 */
class TaskFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'component_id' => ['nullable', 'exists:components,id'],
            'phase_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'dependencies' => ['sometimes', 'array'],
            'dependencies.*' => ['integer', 'exists:tasks,id'],
            'conditional_tag' => ['nullable', 'string', 'max:100'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'ID dự án là bắt buộc.',
            'project_id.exists' => 'Dự án không tồn tại.',
            'component_id.exists' => 'Component không tồn tại.',
            'name.required' => 'Tên task là bắt buộc.',
            'name.max' => 'Tên task không được vượt quá 255 ký tự.',
            'start_date.required' => 'Ngày bắt đầu là bắt buộc.',
            'end_date.required' => 'Ngày kết thúc là bắt buộc.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'dependencies.array' => 'Dependencies phải là mảng.',
            'dependencies.*.exists' => 'Task dependency không tồn tại.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra component thuộc cùng project
            if ($this->component_id && $this->project_id) {
                $component = \App\Models\Component::find($this->component_id);
                if ($component && $component->project_id !== (int)$this->project_id) {
                    $validator->errors()->add('component_id', 'Component phải thuộc cùng dự án.');
                }
            }

            // Kiểm tra circular dependencies
            if ($this->dependencies && is_array($this->dependencies)) {
                $taskId = $this->route('task');
                if ($taskId && in_array($taskId, $this->dependencies)) {
                    $validator->errors()->add('dependencies', 'Task không thể phụ thuộc vào chính nó.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert dependencies JSON string to array if needed
        if ($this->has('dependencies') && is_string($this->dependencies)) {
            $this->merge([
                'dependencies' => json_decode($this->dependencies, true) ?: [],
            ]);
        }
    }
}
<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho InteractionLog validation
 * 
 * @package App\Http\Requests
 */
class InteractionLogFormRequest extends FormRequest
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
            'linked_task_id' => ['nullable', 'exists:tasks,id'],
            'type' => ['required', Rule::in(['call', 'email', 'meeting', 'note', 'feedback'])],
            'description' => ['required', 'string', 'max:5000'],
            'tag_path' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::in(['internal', 'client'])],
            'client_approved' => ['sometimes', 'boolean'],
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
            'linked_task_id.exists' => 'Task liên kết không tồn tại.',
            'type.required' => 'Loại tương tác là bắt buộc.',
            'type.in' => 'Loại tương tác không hợp lệ.',
            'description.required' => 'Mô tả là bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 5000 ký tự.',
            'tag_path.max' => 'Tag path không được vượt quá 255 ký tự.',
            'visibility.required' => 'Mức độ hiển thị là bắt buộc.',
            'visibility.in' => 'Mức độ hiển thị không hợp lệ.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra linked_task thuộc cùng project
            if ($this->linked_task_id && $this->project_id) {
                $task = \App\Models\Task::find($this->linked_task_id);
                if ($task && $task->project_id !== (int)$this->project_id) {
                    $validator->errors()->add('linked_task_id', 'Task liên kết phải thuộc cùng dự án.');
                }
            }

            // Nếu visibility = 'client' thì cần client_approved = true để hiển thị
            if ($this->visibility === 'client' && !$this->client_approved) {
                // Đây là warning, không phải error - chỉ thông báo
                // Có thể log hoặc thêm vào response metadata
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Thêm created_by từ authenticated user
        if (auth()->check()) {
            $this->merge([
                'created_by' => auth()->id(),
            ]);
        }

        // Mặc định client_approved = false nếu visibility = 'client'
        if ($this->visibility === 'client' && !$this->has('client_approved')) {
            $this->merge([
                'client_approved' => false,
            ]);
        }
    }
}
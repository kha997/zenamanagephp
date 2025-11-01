<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request cho TaskAssignment validation
 * 
 * @package App\Http\Requests
 */
class TaskAssignmentFormRequest extends FormRequest
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
            'task_id' => ['required', 'exists:tasks,id'],
            'user_id' => ['required', 'exists:users,id'],
            'split_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'task_id.required' => 'ID task là bắt buộc.',
            'task_id.exists' => 'Task không tồn tại.',
            'user_id.required' => 'ID user là bắt buộc.',
            'user_id.exists' => 'User không tồn tại.',
            'split_percentage.required' => 'Phần trăm phân chia là bắt buộc.',
            'split_percentage.numeric' => 'Phần trăm phân chia phải là số.',
            'split_percentage.min' => 'Phần trăm phân chia phải lớn hơn 0.',
            'split_percentage.max' => 'Phần trăm phân chia không được vượt quá 100%.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra tổng split_percentage của task không vượt quá 100%
            if ($this->task_id) {
                $currentTotal = \App\Models\TaskAssignment::where('task_id', $this->task_id)
                    ->when($this->route('assignment'), function ($query, $assignmentId) {
                        return $query->where('id', '!=', $assignmentId);
                    })
                    ->sum('split_percentage');
                
                $newTotal = $currentTotal + (float)$this->split_percentage;
                
                if ($newTotal > 100) {
                    $validator->errors()->add('split_percentage', 
                        'Tổng phần trăm phân chia cho task này không được vượt quá 100%. ' .
                        'Hiện tại: ' . $currentTotal . '%, còn lại: ' . (100 - $currentTotal) . '%'
                    );
                }
            }
        });
    }
}
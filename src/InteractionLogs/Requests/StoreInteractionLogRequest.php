<?php declare(strict_types=1);

namespace App\InteractionLogs\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\InteractionLogs\Models\InteractionLog;

/**
 * Form Request cho việc tạo Interaction Log mới
 */
class StoreInteractionLogRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return true; // Sẽ được xử lý bởi middleware RBAC
    }

    /**
     * Các rules validation
     */
    public function rules(): array
    {
        return [
            'project_id' => 'required|integer|exists:projects,id',
            'linked_task_id' => 'nullable|integer|exists:tasks,id',
            'type' => 'required|string|in:' . implode(',', array_keys(InteractionLog::TYPES)),
            'description' => 'required|string|max:65535',
            'tag_path' => 'nullable|string|max:255',
            'visibility' => 'required|string|in:internal,client',
            'client_approved' => 'boolean'
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Project ID là bắt buộc',
            'project_id.exists' => 'Project không tồn tại',
            'linked_task_id.exists' => 'Task không tồn tại',
            'type.required' => 'Loại tương tác là bắt buộc',
            'type.in' => 'Loại tương tác không hợp lệ',
            'description.required' => 'Mô tả là bắt buộc',
            'description.max' => 'Mô tả không được vượt quá 65535 ký tự',
            'tag_path.max' => 'Tag path không được vượt quá 255 ký tự',
            'visibility.required' => 'Mức độ hiển thị là bắt buộc',
            'visibility.in' => 'Mức độ hiển thị không hợp lệ'
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Nếu visibility là client thì mặc định client_approved = false
        if ($this->visibility === 'client' && !$this->has('client_approved')) {
            $this->merge(['client_approved' => false]);
        }
    }
}
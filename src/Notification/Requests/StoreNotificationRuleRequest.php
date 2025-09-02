<?php declare(strict_types=1);

namespace Src\Notification\Requests;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
/**
 * Form Request để xác thực dữ liệu khi tạo Notification Rule mới
 * 
 * @package Src\Notification\Requests
 */
class StoreNotificationRuleRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return true; // Authorization được xử lý bởi RBAC middleware
    }

    /**
     * Các quy tắc validation cho request
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id'
            ],
            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id'
            ],
            'event_key' => [
                'required',
                'string',
                'max:100'
            ],
            'min_priority' => [
                'required',
                'string',
                Rule::in(['critical', 'normal', 'low'])
            ],
            'channels' => [
                'required',
                'array',
                'min:1'
            ],
            'channels.*' => [
                'required',
                'string',
                Rule::in(['inapp', 'email', 'webhook'])
            ],
            'conditions' => [
                'nullable',
                'array'
            ],
            'conditions.*.field' => [
                'required_with:conditions',
                'string',
                'max:100'
            ],
            'conditions.*.operator' => [
                'required_with:conditions',
                'string',
                Rule::in(['=', '!=', '>', '<', '>=', '<=', 'contains', 'not_contains', 'in', 'not_in'])
            ],
            'conditions.*.value' => [
                'required_with:conditions'
            ],
            'is_enabled' => [
                'sometimes',
                'boolean'
            ]
        ];
    }

    /**
     * Các thông báo lỗi tùy chỉnh
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID là bắt buộc.',
            'user_id.exists' => 'User không tồn tại.',
            'project_id.exists' => 'Project không tồn tại.',
            'event_key.required' => 'Event key là bắt buộc.',
            'event_key.max' => 'Event key không được vượt quá 100 ký tự.',
            'min_priority.required' => 'Mức độ ưu tiên tối thiểu là bắt buộc.',
            'min_priority.in' => 'Mức độ ưu tiên phải là: critical, normal, hoặc low.',
            'channels.required' => 'Ít nhất một kênh gửi là bắt buộc.',
            'channels.min' => 'Phải có ít nhất một kênh gửi.',
            'channels.*.in' => 'Kênh gửi phải là: inapp, email, hoặc webhook.',
            'conditions.*.field.required_with' => 'Trường điều kiện là bắt buộc.',
            'conditions.*.operator.required_with' => 'Toán tử điều kiện là bắt buộc.',
            'conditions.*.operator.in' => 'Toán tử phải là một trong: =, !=, >, <, >=, <=, contains, not_contains, in, not_in.',
            'conditions.*.value.required_with' => 'Giá trị điều kiện là bắt buộc.'
        ];
    }

    /**
     * Lấy ID của user hiện tại một cách an toàn
     * 
     * @return int|null
     */
    private function getUserId(): ?int
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
        } catch (\Exception $e) {
            // Log error nếu cần thiết
            // Log::warning('Cannot get user ID: ' . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Chuẩn bị dữ liệu cho validation
     */
    protected function prepareForValidation(): void
    {
        // Nếu không có user_id, sử dụng user hiện tại
        if (!$this->has('user_id')) {
            $userId = $this->getUserId();
            if ($userId !== null) {
                $this->merge([
                    'user_id' => $userId
                ]);
            }
        }

        // Mặc định is_enabled là true nếu không được chỉ định
        if (!$this->has('is_enabled')) {
            $this->merge([
                'is_enabled' => true
            ]);
        }
    }

    /**
     * Xử lý sau khi validation thành công
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate event_key format
            if ($this->has('event_key')) {
                $eventKey = $this->input('event_key');
                if (!preg_match('/^[A-Za-z]+\.[A-Za-z]+\.[A-Za-z]+$/', $eventKey)) {
                    $validator->errors()->add('event_key', 'Event key phải có định dạng Domain.Entity.Action (ví dụ: Project.Task.Created)');
                }
            }

            // Validate channels array không rỗng
            if ($this->has('channels') && empty($this->input('channels'))) {
                $validator->errors()->add('channels', 'Phải có ít nhất một kênh gửi.');
            }
        });
    }
}
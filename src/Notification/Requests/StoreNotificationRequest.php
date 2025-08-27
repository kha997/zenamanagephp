<?php declare(strict_types=1);

namespace Src\Notification\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi tạo Notification mới
 * 
 * @package Src\Notification\Requests
 */
class StoreNotificationRequest extends FormRequest
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
            'priority' => [
                'required',
                'string',
                Rule::in(['critical', 'normal', 'low'])
            ],
            'title' => [
                'required',
                'string',
                'max:255'
            ],
            'body' => [
                'required',
                'string',
                'max:5000'
            ],
            'link_url' => [
                'nullable',
                'string',
                'url',
                'max:500'
            ],
            'channel' => [
                'required',
                'string',
                Rule::in(['inapp', 'email', 'webhook'])
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
            'priority.required' => 'Mức độ ưu tiên là bắt buộc.',
            'priority.in' => 'Mức độ ưu tiên phải là: critical, normal, hoặc low.',
            'title.required' => 'Tiêu đề là bắt buộc.',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự.',
            'body.required' => 'Nội dung là bắt buộc.',
            'body.max' => 'Nội dung không được vượt quá 5000 ký tự.',
            'link_url.url' => 'Link URL phải có định dạng hợp lệ.',
            'link_url.max' => 'Link URL không được vượt quá 500 ký tự.',
            'channel.required' => 'Kênh gửi là bắt buộc.',
            'channel.in' => 'Kênh gửi phải là: inapp, email, hoặc webhook.'
        ];
    }

    /**
     * Chuẩn bị dữ liệu cho validation
     */
    protected function prepareForValidation(): void
    {
        // Nếu không có user_id, sử dụng user hiện tại
        if (!$this->has('user_id') && auth()->check()) {
            $this->merge([
                'user_id' => auth()->id()
            ]);
        }

        // Mặc định channel là inapp nếu không được chỉ định
        if (!$this->has('channel')) {
            $this->merge([
                'channel' => 'inapp'
            ]);
        }

        // Mặc định priority là normal nếu không được chỉ định
        if (!$this->has('priority')) {
            $this->merge([
                'priority' => 'normal'
            ]);
        }
    }
}
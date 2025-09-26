<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi cập nhật Notification
 * 
 * @package Src\Notification\Requests
 */
class UpdateNotificationRequest extends FormRequest
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
            'priority' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['critical', 'normal', 'low'])
            ],
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'body' => [
                'sometimes',
                'required',
                'string',
                'max:5000'
            ],
            'link_url' => [
                'sometimes',
                'nullable',
                'string',
                'url',
                'max:500'
            ],
            'channel' => [
                'sometimes',
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
}
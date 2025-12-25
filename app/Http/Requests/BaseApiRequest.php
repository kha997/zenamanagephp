<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base API Request với validation messages tiếng Việt
 */
abstract class BaseApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Override in child classes if needed
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void {
        throw new HttpResponseException(
            response()->json([
                'status' => 'fail',
                'message' => 'Dữ liệu không hợp lệ',
                'data' => [
                    'validation_errors' => $validator->errors()->toArray()
                ]
            ], 422)
        );
    }

    /**
     * Get custom validation messages in Vietnamese
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'required' => 'Trường :attribute là bắt buộc.',
            'string' => 'Trường :attribute phải là chuỗi ký tự.',
            'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
            'unique' => 'Giá trị :attribute đã tồn tại.',
            'exists' => 'Giá trị :attribute không tồn tại.',
            'min' => 'Trường :attribute phải có ít nhất :min ký tự.',
            'max' => 'Trường :attribute không được vượt quá :max ký tự.',
            'numeric' => 'Trường :attribute phải là số.',
            'integer' => 'Trường :attribute phải là số nguyên.',
            'boolean' => 'Trường :attribute phải là true hoặc false.',
            'date' => 'Trường :attribute phải là ngày hợp lệ.',
            'date_format' => 'Trường :attribute phải có định dạng :format.',
            'in' => 'Giá trị được chọn cho :attribute không hợp lệ.',
            'array' => 'Trường :attribute phải là mảng.',
            'json' => 'Trường :attribute phải là JSON hợp lệ.',
            'ulid' => 'Trường :attribute phải là ULID hợp lệ.',
            'confirmed' => 'Xác nhận :attribute không khớp.',
            'same' => 'Trường :attribute và :other phải giống nhau.',
            'different' => 'Trường :attribute và :other phải khác nhau.',
            'before' => 'Trường :attribute phải là ngày trước :date.',
            'after' => 'Trường :attribute phải là ngày sau :date.',
            'alpha' => 'Trường :attribute chỉ được chứa chữ cái.',
            'alpha_num' => 'Trường :attribute chỉ được chứa chữ cái và số.',
            'regex' => 'Định dạng trường :attribute không hợp lệ.',
            'size' => 'Trường :attribute phải có kích thước :size.',
            'between' => 'Trường :attribute phải nằm trong khoảng :min và :max.',
            'digits' => 'Trường :attribute phải có :digits chữ số.',
            'digits_between' => 'Trường :attribute phải có từ :min đến :max chữ số.',
            'file' => 'Trường :attribute phải là file.',
            'image' => 'Trường :attribute phải là hình ảnh.',
            'mimes' => 'Trường :attribute phải là file có định dạng: :values.',
            'mimetypes' => 'Trường :attribute phải là file có loại: :values.',
            'uploaded' => 'Tải lên :attribute thất bại.',
        ];
    }

    /**
     * Get custom attribute names in Vietnamese
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'name' => 'tên',
            'email' => 'email',
            'password' => 'mật khẩu',
            'password_confirmation' => 'xác nhận mật khẩu',
            'description' => 'mô tả',
            'start_date' => 'ngày bắt đầu',
            'end_date' => 'ngày kết thúc',
            'status' => 'trạng thái',
            'priority' => 'độ ưu tiên',
            'progress' => 'tiến độ',
            'cost' => 'chi phí',
            'planned_cost' => 'chi phí dự kiến',
            'actual_cost' => 'chi phí thực tế',
            'estimated_hours' => 'số giờ ước tính',
            'actual_hours' => 'số giờ thực tế',
            'project_id' => 'ID dự án',
            'component_id' => 'ID thành phần',
            'task_id' => 'ID công việc',
            'user_id' => 'ID người dùng',
            'tenant_id' => 'ID tenant',
            'created_by' => 'người tạo',
            'updated_by' => 'người cập nhật',
            'visibility' => 'quyền xem',
            'tags' => 'thẻ',
            'category' => 'danh mục',
            'version' => 'phiên bản',
            'template_data' => 'dữ liệu template',
            'conditional_tag' => 'thẻ điều kiện',
            'dependencies' => 'phụ thuộc',
            'split_percentage' => 'phần trăm phân chia',
            'role' => 'vai trò',
        ];
    }
}
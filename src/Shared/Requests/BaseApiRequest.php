<?php declare(strict_types=1);

namespace Src\Shared\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * Base API Request với validation messages tiếng Việt
 * Shared version cho các modules trong src/
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
    protected function failedValidation(Validator $validator): void
    {
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
     */
    public function messages(): array
    {
        return [
            'required' => 'Trường :attribute là bắt buộc.',
            'string' => 'Trường :attribute phải là chuỗi ký tự.',
            'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
            'unique' => 'Trường :attribute đã tồn tại.',
            'exists' => 'Trường :attribute không tồn tại.',
            'min' => 'Trường :attribute phải có ít nhất :min ký tự.',
            'max' => 'Trường :attribute không được vượt quá :max ký tự.',
            'numeric' => 'Trường :attribute phải là số.',
            'integer' => 'Trường :attribute phải là số nguyên.',
            'boolean' => 'Trường :attribute phải là true hoặc false.',
            'date' => 'Trường :attribute phải là ngày hợp lệ.',
            'array' => 'Trường :attribute phải là mảng.',
            'in' => 'Trường :attribute không hợp lệ.',
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
}
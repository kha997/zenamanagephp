<?php declare(strict_types=1);

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form Request để validate dữ liệu tạo user mới
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization sẽ được xử lý bởi RBAC middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => [
                'required', 
                'string', 
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
                'confirmed'
            ],
            'tenant_id' => ['required', 'exists:tenants,id']
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc',
            'email.required' => 'Email là bắt buộc',
            'email.unique' => 'Email đã được sử dụng',
            'password.required' => 'Mật khẩu là bắt buộc',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự',
            'password.letters' => 'Mật khẩu phải chứa ít nhất một chữ cái',
            'password.mixed' => 'Mật khẩu phải chứa cả chữ hoa và chữ thường',
            'password.numbers' => 'Mật khẩu phải chứa ít nhất một số',
            'password.symbols' => 'Mật khẩu phải chứa ít nhất một ký tự đặc biệt',
            'password.uncompromised' => 'Mật khẩu này đã bị lộ trong các vụ rò rỉ dữ liệu',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',
            'tenant_id.required' => 'Tenant ID là bắt buộc',
            'tenant_id.exists' => 'Tenant không tồn tại'
        ];
    }
}
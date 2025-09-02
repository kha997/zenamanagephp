<?php declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để validate dữ liệu đăng ký
 */
class RegisterRequest extends FormRequest
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
            // User data
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            
            // Tenant data
            'company_name' => ['required', 'string', 'max:255'],
            'company_domain' => [
                'nullable', 
                'string', 
                'max:100', 
                Rule::unique('tenants', 'domain')->whereNotNull('domain')
            ],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_address' => ['nullable', 'string', 'max:500']
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
            'password.confirmed' => 'Xác nhận mật khẩu không khớp',
            'company_name.required' => 'Tên công ty là bắt buộc'
        ];
    }

    /**
     * Get user data from request
     */
    public function getUserData(): array
    {
        return $this->only(['name', 'email', 'password']);
    }

    /**
     * Get tenant data from request
     */
    public function getTenantData(): array
    {
        return [
            'name' => $this->input('company_name'),
            'domain' => $this->input('company_domain'),
            'phone' => $this->input('company_phone'),
            'address' => $this->input('company_address')
        ];
    }
}
<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * User Form Request
 * 
 * Handles validation logic for User creation and updates
 */
class UserFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($userId)
            ],
            'password' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
            'password_confirmation' => 'required_with:password|same:password',
            'system_roles' => 'nullable|array',
            'system_roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('scope', 'system')
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên người dùng là bắt buộc.',
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password_confirmation.required_with' => 'Xác nhận mật khẩu là bắt buộc.',
            'password_confirmation.same' => 'Xác nhận mật khẩu không khớp.',
            'system_roles.*.exists' => 'Role không tồn tại hoặc không phải system role.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Auto-assign tenant_id from authenticated user
        $this->merge([
            'tenant_id' => auth()->user()->tenant_id,
        ]);
    }
}
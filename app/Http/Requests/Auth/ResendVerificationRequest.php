<?php declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResendVerificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/controller logic
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // If user is authenticated, email is optional (use authenticated user's email)
        // If user is not authenticated, email is required
        $rules = [];
        
        if (!$this->user()) {
            $rules['email'] = [
                'required',
                'email',
                'max:255',
                // Note: exists check is handled in controller to return proper 404
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address must not exceed 255 characters.',
            'email.exists' => 'No account found with this email address.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email address',
        ];
    }

    /**
     * Get the email to resend verification for
     */
    public function getEmail(): string
    {
        // If authenticated, use authenticated user's email
        if ($this->user()) {
            return $this->user()->email;
        }

        // Otherwise, use provided email
        return $this->input('email');
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user has admin ability
        if (!auth()->user()->hasRole('admin')) {
            return false;
        }

        // Check for sensitive feature flag changes
        $featureFlags = $this->input('feature_flags', []);
        if (isset($featureFlags['mfa']) && $featureFlags['mfa'] !== $this->getCurrentMfaSetting()) {
            // MFA changes require security_admin role
            return auth()->user()->hasRole('security_admin');
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'app_name' => [
                'required',
                'string',
                'min:2',
                'max:40',
                'regex:/^[a-zA-Z0-9\s\-_]+$/' // Allow alphanumeric, spaces, hyphens, underscores
            ],
            'email_sender' => [
                'required',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Block temporary email domains
                    $blockedDomains = ['@example.com', '@test.com', '@localhost'];
                    foreach ($blockedDomains as $domain) {
                        if (str_ends_with($value, $domain)) {
                            $fail('Temporary email domains are not allowed.');
                            return;
                        }
                    }
                }
            ],
            'feature_flags' => 'required|array',
            'feature_flags.mfa' => 'required|boolean',
            'feature_flags.analytics' => 'required|boolean',
        ];
    }

    /**
     * Get custom error messages
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'app_name.required' => 'Application name is required.',
            'app_name.min' => 'Application name must be at least 2 characters.',
            'app_name.max' => 'Application name cannot exceed 40 characters.',
            'app_name.regex' => 'Application name can only contain letters, numbers, spaces, hyphens, and underscores.',
            'email_sender.required' => 'Email sender address is required.',
            'email_sender.email' => 'Please provide a valid email address.',
            'email_sender.max' => 'Email address cannot exceed 255 characters.',
            'feature_flags.required' => 'Feature flags are required.',
            'feature_flags.mfa.required' => 'MFA feature flag is required.',
            'feature_flags.mfa.boolean' => 'MFA feature flag must be true or false.',
            'feature_flags.analytics.required' => 'Analytics feature flag is required.',
            'feature_flags.analytics.boolean' => 'Analytics feature flag must be true or false.',
        ];
    }

    /**
     * Get the current MFA setting from database
     *
     * @return bool
     */
    private function getCurrentMfaSetting(): bool
    {
        $setting = \DB::table('system_settings')
            ->where('key', 'feature_flags')
            ->value('value');

        if ($setting) {
            $flags = json_decode($setting, true);
            return $flags['mfa'] ?? config('features.mfa_enabled', true);
        }

        return config('features.mfa_enabled', true);
    }
}

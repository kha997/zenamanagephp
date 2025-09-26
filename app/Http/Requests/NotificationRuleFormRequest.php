<?php declare(strict_types=1);

namespace App\Http\Requests;
use Illuminate\Support\Facades\Auth;


use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for notification rule validation
 * Handles validation logic for creating and updating notification rules
 */
class NotificationRuleFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     *
     * @return bool Authorization status
     */
    public function authorize(): bool
    {
        return Auth::check();
    }
    
    /**
     * Get the validation rules that apply to the request
     *
     * @return array<string, mixed> Validation rules
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');
        
        return [
            'event_key' => $isUpdate ? 'sometimes|string|max:100' : 'required|string|max:100',
            'project_id' => 'nullable|integer|exists:projects,id',
            'min_priority' => 'sometimes|in:low,normal,critical',
            'channels' => 'sometimes|array|min:1',
            'channels.*' => 'in:inapp,email,webhook',
            'is_enabled' => 'sometimes|boolean'
        ];
    }
    
    /**
     * Get custom error messages for validation rules
     *
     * @return array<string, string> Custom error messages
     */
    public function messages(): array
    {
        return [
            'event_key.required' => 'Event key is required.',
            'event_key.string' => 'Event key must be a string.',
            'event_key.max' => 'Event key cannot exceed 100 characters.',
            'project_id.integer' => 'Project ID must be an integer.',
            'project_id.exists' => 'Selected project does not exist.',
            'min_priority.in' => 'Priority must be one of: low, normal, critical.',
            'channels.array' => 'Channels must be an array.',
            'channels.min' => 'At least one channel must be selected.',
            'channels.*.in' => 'Invalid channel selected. Available channels: inapp, email, webhook.',
            'is_enabled.boolean' => 'Enabled status must be true or false.'
        ];
    }
    
    /**
     * Prepare the data for validation
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        if (!$this->has('min_priority')) {
            $this->merge(['min_priority' => 'normal']);
        }
        
        if (!$this->has('channels')) {
            $this->merge(['channels' => ['inapp']]);
        }
        
        if (!$this->has('is_enabled')) {
            $this->merge(['is_enabled' => true]);
        }
    }
}
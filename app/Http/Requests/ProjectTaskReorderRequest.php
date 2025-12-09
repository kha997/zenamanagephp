<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ProjectTaskReorderRequest
 * 
 * Round 210: Form request for reordering project tasks within a phase
 * 
 * Validates the ordered_ids array for task reordering
 */
class ProjectTaskReorderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'ordered_ids.*' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $orderedIds = $this->input('ordered_ids', []);
            
            // Check for duplicate IDs
            $uniqueIds = array_unique($orderedIds);
            if (count($uniqueIds) !== count($orderedIds)) {
                $validator->errors()->add(
                    'ordered_ids',
                    'The ordered_ids array contains duplicate task IDs.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'ordered_ids.required' => 'The ordered_ids field is required.',
            'ordered_ids.array' => 'The ordered_ids must be an array.',
            'ordered_ids.min' => 'The ordered_ids array must contain at least one task ID.',
            'ordered_ids.*.required' => 'Each task ID in ordered_ids is required.',
            'ordered_ids.*.string' => 'Each task ID must be a string.',
            'ordered_ids.*.max' => 'Each task ID must not exceed 255 characters.',
        ];
    }
}


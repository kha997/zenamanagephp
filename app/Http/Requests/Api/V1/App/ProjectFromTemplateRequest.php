<?php declare(strict_types=1);

namespace App\Http\Requests\Api\V1\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request for creating a project from a template
 */
class ProjectFromTemplateRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'medium', 'high', 'urgent'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_total' => ['nullable', 'numeric', 'min:0'],
            'owner_id' => ['nullable', 'string', 'exists:users,id'],
            'client_id' => ['nullable', 'string', 'exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}


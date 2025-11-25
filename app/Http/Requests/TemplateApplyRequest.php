<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TemplateApplyRequest
 * 
 * Form request for template application validation.
 */
class TemplateApplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'set_id' => 'required|string|exists:template_sets,id',
            'preset_code' => 'nullable|string',
            'selections' => 'nullable|array',
            'selections.phases' => 'nullable|array',
            'selections.phases.*' => 'string',
            'selections.disciplines' => 'nullable|array',
            'selections.disciplines.*' => 'string',
            'selections.tasks' => 'nullable|array',
            'selections.tasks.*' => 'string',
            'options' => 'nullable|array',
            'options.conflict_behavior' => 'nullable|in:skip,rename,merge',
            'options.map_phase_to_kanban' => 'nullable|boolean',
            'options.auto_assign_by_role' => 'nullable|boolean',
            'options.create_deliverable_folders' => 'nullable|boolean',
        ];
    }
}


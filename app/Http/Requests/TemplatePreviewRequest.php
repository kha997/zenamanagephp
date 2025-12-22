<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TemplatePreviewRequest
 * 
 * Form request for template preview validation.
 */
class TemplatePreviewRequest extends FormRequest
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
            'project_id' => 'required|string|exists:projects,id',
            'preset_code' => 'nullable|string',
            'selections' => 'nullable|array',
            'selections.phases' => 'nullable|array',
            'selections.phases.*' => 'string',
            'selections.disciplines' => 'nullable|array',
            'selections.disciplines.*' => 'string',
            'selections.tasks' => 'nullable|array',
            'selections.tasks.*' => 'string',
            'options' => 'nullable|array',
            'options.map_phase_to_kanban' => 'nullable|boolean',
            'options.auto_assign_by_role' => 'nullable|boolean',
            'options.create_deliverable_folders' => 'nullable|boolean',
        ];
    }
}


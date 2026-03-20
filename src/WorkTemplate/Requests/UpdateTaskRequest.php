<?php declare(strict_types=1);

namespace Src\WorkTemplate\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\WorkTemplate\Models\ProjectTask;

final class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phase_id' => ['sometimes', 'nullable', 'string'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'duration_days' => ['sometimes', 'integer', 'min:0'],
            'progress_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(ProjectTask::getAvailableStatuses())],
            'conditional_tag' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_hidden' => ['sometimes', 'boolean'],
        ];
    }
}

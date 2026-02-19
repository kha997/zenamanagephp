<?php declare(strict_types=1);

namespace Src\WorkTemplate\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ToggleConditionalRequest extends FormRequest
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
            'is_visible' => ['nullable', 'boolean'],
        ];
    }
}

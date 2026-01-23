<?php declare(strict_types=1);

namespace Src\WorkTemplate\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleConditionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_visible' => 'nullable|boolean',
        ];
    }
}

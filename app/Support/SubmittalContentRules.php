<?php declare(strict_types=1);

namespace App\Support;

final class SubmittalContentRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'submittal_type' => ['sometimes', 'in:shop_drawing,material_sample,product_data,test_report,other'],
            'specification_section' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'contractor' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
        ];
    }
}

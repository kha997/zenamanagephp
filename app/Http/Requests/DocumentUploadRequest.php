<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class DocumentUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB max
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'])
            ],
            'project_id' => 'nullable|string|exists:projects,id',
            'category' => 'nullable|string|in:general,contract,drawing,specification,report,other',
            'description' => 'nullable|string|max:1000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => 'The file size cannot exceed 100MB.',
            'file.mimes' => 'The file must be one of the allowed types: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, jpg, jpeg, png, gif, zip, rar.',
            'project_id.exists' => 'The selected project does not exist.',
            'category.in' => 'The category must be one of: general, contract, drawing, specification, report, other.',
            'description.max' => 'The description cannot exceed 1000 characters.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'is_public.boolean' => 'The is_public field must be true or false.',
            'requires_approval.boolean' => 'The requires_approval field must be true or false.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'file' => 'document file',
            'project_id' => 'project',
            'category' => 'document category',
            'description' => 'document description',
            'tags' => 'document tags',
            'is_public' => 'public visibility',
            'requires_approval' => 'approval requirement'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure project_id belongs to user's tenant
        if ($this->project_id) {
            $project = \App\Models\Project::where('id', $this->project_id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->first();
                
            if (!$project) {
                $this->merge(['project_id' => null]);
            }
        }
    }
}

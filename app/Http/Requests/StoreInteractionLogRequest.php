<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Project;
use App\Models\Task;

class StoreInteractionLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization sẽ được xử lý bởi middleware và policies
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                'size:26', // ULID length
                Rule::exists('projects', 'id')->where(function ($query) {
                    $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'linked_task_id' => [
                'nullable',
                'string',
                'size:26',
                function ($attribute, $value, $fail) {
                    if ($value && $this->project_id) {
                        $task = Task::where('id', $value)
                            ->where('project_id', $this->project_id)
                            ->first();
                        
                        if (!$task) {
                            $fail('Task không tồn tại trong project này.');
                        }
                    }
                }
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['call', 'email', 'meeting', 'note', 'feedback'])
            ],
            'description' => [
                'required',
                'string',
                'max:65535' // TEXT field limit
            ],
            'tag_path' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\/\-_\s]+$/' // Chỉ cho phép ký tự hợp lệ cho tag path
            ],
            'visibility' => [
                'required',
                'string',
                Rule::in(['internal', 'client'])
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:10' // Giới hạn tối đa 10 file
            ],
            'attachments.*' => [
                'file',
                'max:10240', // 10MB per file
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,zip,rar'
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Project ID là bắt buộc.',
            'project_id.exists' => 'Project không tồn tại hoặc bạn không có quyền truy cập.',
            'type.required' => 'Loại interaction log là bắt buộc.',
            'type.in' => 'Loại interaction log không hợp lệ.',
            'description.required' => 'Mô tả là bắt buộc.',
            'description.max' => 'Mô tả không được vượt quá 65535 ký tự.',
            'tag_path.regex' => 'Tag path chỉ được chứa chữ cái, số, dấu gạch ngang, gạch dưới và dấu cách.',
            'visibility.required' => 'Mức độ hiển thị là bắt buộc.',
            'visibility.in' => 'Mức độ hiển thị không hợp lệ.',
            'attachments.max' => 'Không được đính kèm quá 10 file.',
            'attachments.*.file' => 'File đính kèm không hợp lệ.',
            'attachments.*.max' => 'Mỗi file không được vượt quá 10MB.',
            'attachments.*.mimes' => 'File đính kèm phải có định dạng: pdf, doc, docx, xls, xlsx, jpg, jpeg, png, gif, zip, rar.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'project_id' => 'Project ID',
            'linked_task_id' => 'Task ID',
            'type' => 'Loại',
            'description' => 'Mô tả',
            'tag_path' => 'Tag path',
            'visibility' => 'Mức độ hiển thị',
            'attachments' => 'File đính kèm'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Chuẩn hóa dữ liệu trước khi validate
        if ($this->has('tag_path')) {
            $this->merge([
                'tag_path' => trim($this->tag_path)
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => trim($this->description)
            ]);
        }
    }
}
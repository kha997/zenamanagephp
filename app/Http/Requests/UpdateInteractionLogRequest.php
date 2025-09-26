<?php declare(strict_types=1);

namespace App\Http\Requests;
use Illuminate\Support\Facades\Auth;


use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInteractionLogRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $interactionLog = $this->route('interaction_log');
        
        return [
            'project_id' => [
                'sometimes',
                'string',
                'size:26',
                Rule::exists('projects', 'id')->where(function ($query) {
                    $query->where('tenant_id', Auth::user()->tenant_id);
                })
            ],
            'linked_task_id' => [
                'nullable',
                'string',
                'size:26',
                function ($attribute, $value, $fail) {
                    $projectId = $this->project_id ?? $this->route('interaction_log')->project_id;
                    
                    if ($value && $projectId) {
                        $task = Task::where('id', $value)
                            ->where('project_id', $projectId)
                            ->first();
                        
                        if (!$task) {
                            $fail('Task không tồn tại trong project này.');
                        }
                    }
                }
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::in(['call', 'email', 'meeting', 'note', 'feedback'])
            ],
            'description' => [
                'sometimes',
                'string',
                'max:65535'
            ],
            'tag_path' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\/\-_\s]+$/'
            ],
            'visibility' => [
                'sometimes',
                'string',
                Rule::in(['internal', 'client']),
                function ($attribute, $value, $fail) {
                    // Không cho phép thay đổi từ 'client' sang 'internal' nếu đã được approve
                    if ($interactionLog && 
                        $interactionLog->visibility === 'client' && 
                        $interactionLog->client_approved && 
                        $value === 'internal') {
                        $fail('Không thể thay đổi visibility từ client sang internal khi đã được phê duyệt.');
                    }
                }
            ],
            'client_approved' => [
                'sometimes',
                'boolean',
                function ($attribute, $value, $fail) {
                    // Chỉ cho phép approve nếu visibility là 'client'
                    $visibility = $this->visibility ?? $interactionLog->visibility;
                    if ($value && $visibility !== 'client') {
                        $fail('Chỉ có thể approve interaction log với visibility = client.');
                    }
                }
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:10'
            ],
            'attachments.*' => [
                'file',
                'max:10240',
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
            'project_id.exists' => 'Project không tồn tại hoặc bạn không có quyền truy cập.',
            'type.in' => 'Loại interaction log không hợp lệ.',
            'description.max' => 'Mô tả không được vượt quá 65535 ký tự.',
            'tag_path.regex' => 'Tag path chỉ được chứa chữ cái, số, dấu gạch ngang, gạch dưới và dấu cách.',
            'visibility.in' => 'Mức độ hiển thị không hợp lệ.',
            'client_approved.boolean' => 'Trạng thái phê duyệt phải là true hoặc false.',
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
            'client_approved' => 'Trạng thái phê duyệt',
            'attachments' => 'File đính kèm'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
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
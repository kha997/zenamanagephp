<?php declare(strict_types=1);

$requests = [
    'StoreProjectRequest' => [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'start_date' => 'required|date|after_or_equal:today',
        'end_date' => 'required|date|after:start_date',
        'budget' => 'nullable|numeric|min:0',
        'status' => 'required|in:planning,active,on_hold,completed,cancelled'
    ],
    'StoreTaskRequest' => [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'project_id' => 'required|exists:projects,id',
        'assignee_id' => 'nullable|exists:users,id',
        'priority' => 'required|in:low,medium,high,urgent',
        'due_date' => 'nullable|date|after:today'
    ],
    'StoreDocumentRequest' => [
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        'category' => 'required|string|max:100'
    ]
];

foreach ($requests as $className => $rules) {
    $content = generateRequestClass($className, $rules);
    file_put_contents("app/Http/Requests/$className.php", $content);
    echo "✅ Created: $className\n";
}

function generateRequestClass($className, $rules) {
    $rulesString = var_export($rules, true);
    
    return "<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class $className extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle in controller or policy
    }

    public function rules(): array
    {
        return $rulesString;
    }
}";
}
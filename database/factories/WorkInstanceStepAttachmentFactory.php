<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkInstanceStepAttachment>
 */
class WorkInstanceStepAttachmentFactory extends Factory
{
    protected $model = WorkInstanceStepAttachment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_instance_step_id' => WorkInstanceStep::factory(),
            'file_name' => 'attachment.txt',
            'file_path' => 'work-instances/' . Str::lower((string) Str::ulid()) . '.txt',
            'mime_type' => 'text/plain',
            'file_size' => 0,
            'uploaded_by' => User::factory(),
        ];
    }
}

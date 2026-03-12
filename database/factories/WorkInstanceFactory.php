<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkInstance>
 */
class WorkInstanceFactory extends Factory
{
    protected $model = WorkInstance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'scope_type' => 'project',
            'scope_id' => fn (array $attributes): string => (string) $attributes['project_id'],
            'work_template_version_id' => WorkTemplateVersion::factory(),
            'status' => 'pending',
            'apply_fingerprint' => null,
            'created_by' => User::factory(),
        ];
    }
}

<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkTemplateVersion>
 */
class WorkTemplateVersionFactory extends Factory
{
    protected $model = WorkTemplateVersion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'work_template_id' => WorkTemplate::factory(),
            'semver' => 'draft-' . $this->faker->unique()->numerify('###'),
            'content_json' => [
                'steps' => [],
                'approvals' => [],
                'rules' => [],
            ],
            'is_immutable' => false,
            'published_at' => null,
            'published_by' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WorkTemplate>
 */
class WorkTemplateFactory extends Factory
{
    protected $model = WorkTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'WT-' . strtoupper(Str::random(8)),
            'name' => 'Template ' . Str::upper(Str::random(6)),
            'description' => $this->faker->sentence(),
            'status' => 'draft',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

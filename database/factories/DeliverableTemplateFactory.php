<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliverableTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeliverableTemplate>
 */
class DeliverableTemplateFactory extends Factory
{
    protected $model = DeliverableTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => 'DT-' . strtoupper(Str::random(8)),
            'name' => 'Deliverable ' . Str::upper(Str::random(6)),
            'description' => $this->faker->sentence(),
            'status' => 'draft',
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

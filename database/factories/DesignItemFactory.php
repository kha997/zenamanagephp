<?php

namespace Database\Factories;

use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\DesignItem> */
class DesignItemFactory extends Factory
{
    protected $model = DesignItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'name' => $this->faker->words(3, true),
            'item_type' => $this->faker->randomElement(DesignItem::VALID_TYPES),
            'description' => $this->faker->sentence(),
            'review_status' => 'draft',
            'revision_count' => $this->faker->numberBetween(0, 5),
            'created_by' => User::factory(),
        ];
    }
}

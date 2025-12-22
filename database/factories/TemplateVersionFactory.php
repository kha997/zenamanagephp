<?php

namespace Database\Factories;

use App\Models\TemplateVersion;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemplateVersionFactory extends Factory
{
    protected $model = TemplateVersion::class;

    public function definition(): array
    {
        return [
            'id' => Str::ulid(),
            'template_id' => Template::factory(),
            'version' => $this->faker->numberBetween(1, 10),
            'name' => 'Version ' . $this->faker->numberBetween(1, 10),
            'description' => $this->faker->sentence(),
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Phase 1',
                        'duration_days' => $this->faker->numberBetween(5, 15),
                        'tasks' => [
                            [
                                'name' => 'Task 1',
                                'description' => $this->faker->sentence(),
                                'duration_days' => $this->faker->numberBetween(1, 5),
                                'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
                                'estimated_hours' => $this->faker->numberBetween(8, 40)
                            ]
                        ]
                    ]
                ]
            ],
            'changes' => [
                'added' => ['Task 1'],
                'modified' => [],
                'removed' => []
            ],
            'created_by' => User::factory(),
            'is_active' => $this->faker->boolean(20) // 20% chance of being active
        ];
    }

    public function active(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true
        ]);
    }

    public function inactive(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false
        ]);
    }

    public function withChanges(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'changes' => [
                'added' => $this->faker->randomElements(['Task A', 'Task B', 'Task C'], $this->faker->numberBetween(1, 3)),
                'modified' => $this->faker->randomElements(['Task X', 'Task Y'], $this->faker->numberBetween(0, 2)),
                'removed' => $this->faker->randomElements(['Task Z'], $this->faker->numberBetween(0, 1))
            ]
        ]);
    }
}

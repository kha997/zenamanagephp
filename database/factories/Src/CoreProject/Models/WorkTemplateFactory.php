<?php declare(strict_types=1);

namespace Database\Factories\Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\CoreProject\Models\WorkTemplate;

/**
 * Factory cho WorkTemplate model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\CoreProject\Models\WorkTemplate>
 */
class WorkTemplateFactory extends Factory
{
    protected $model = WorkTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'category' => $this->faker->randomElement(WorkTemplate::VALID_CATEGORIES),
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Planning Phase',
                        'duration_days' => 10,
                        'tasks' => [
                            ['name' => 'Requirements Analysis', 'duration' => 5],
                            ['name' => 'Design Review', 'duration' => 3],
                        ]
                    ],
                    [
                        'name' => 'Execution Phase',
                        'duration_days' => 20,
                        'tasks' => [
                            ['name' => 'Implementation', 'duration' => 15],
                            ['name' => 'Testing', 'duration' => 5],
                        ]
                    ]
                ],
                'resources' => [
                    'required_skills' => ['project_management', 'technical_analysis'],
                    'estimated_team_size' => $this->faker->numberBetween(3, 8)
                ]
            ],
            'version' => $this->faker->numberBetween(1, 5),
            'is_active' => $this->faker->boolean(80),
            'description' => $this->faker->paragraph(2),
        ];
    }

    /**
     * State: Design template
     */
    public function design(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => WorkTemplate::CATEGORY_DESIGN,
            'name' => 'Design Work Template',
        ]);
    }

    /**
     * State: Construction template
     */
    public function construction(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => WorkTemplate::CATEGORY_CONSTRUCTION,
            'name' => 'Construction Work Template',
        ]);
    }

    /**
     * State: Active template
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
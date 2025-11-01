<?php

namespace Database\Factories;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TemplateFactory extends Factory
{
    protected $model = Template::class;

    public function definition(): array
    {
        $categories = Template::VALID_CATEGORIES;
        $statuses = Template::VALID_STATUSES;
        
        return [
            'id' => Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true) . ' Template',
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement($categories),
            'template_data' => $this->generateTemplateData(),
            'settings' => [
                'auto_assign' => $this->faker->boolean(),
                'notifications' => $this->faker->boolean(),
                'deadline_buffer' => $this->faker->numberBetween(1, 7)
            ],
            'status' => $this->faker->randomElement($statuses),
            'version' => $this->faker->numberBetween(1, 5),
            'is_public' => $this->faker->boolean(30), // 30% chance of being public
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'usage_count' => $this->faker->numberBetween(0, 100),
            'tags' => $this->faker->randomElements(['urgent', 'standard', 'complex', 'simple', 'review'], $this->faker->numberBetween(1, 3)),
            'metadata' => [
                'source' => $this->faker->randomElement(['manual', 'imported', 'generated']),
                'complexity' => $this->faker->randomElement(['low', 'medium', 'high']),
                'estimated_duration' => $this->faker->numberBetween(1, 30)
            ]
        ];
    }

    public function project(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'category' => Template::CATEGORY_PROJECT,
            'template_data' => [
                'phases' => [
                    [
                        'name' => 'Planning',
                        'duration_days' => 5,
                        'tasks' => [
                            [
                                'name' => 'Project Setup',
                                'description' => 'Initialize project structure',
                                'duration_days' => 2,
                                'priority' => 'high',
                                'estimated_hours' => 16
                            ],
                            [
                                'name' => 'Requirements Gathering',
                                'description' => 'Collect and document requirements',
                                'duration_days' => 3,
                                'priority' => 'high',
                                'estimated_hours' => 24
                            ]
                        ]
                    ],
                    [
                        'name' => 'Development',
                        'duration_days' => 15,
                        'tasks' => [
                            [
                                'name' => 'Core Development',
                                'description' => 'Main development work',
                                'duration_days' => 10,
                                'priority' => 'high',
                                'estimated_hours' => 80
                            ],
                            [
                                'name' => 'Testing',
                                'description' => 'Quality assurance testing',
                                'duration_days' => 5,
                                'priority' => 'medium',
                                'estimated_hours' => 40
                            ]
                        ]
                    ]
                ],
                'milestones' => [
                    [
                        'name' => 'Project Kickoff',
                        'date_offset' => 0,
                        'description' => 'Project initiation milestone'
                    ],
                    [
                        'name' => 'Development Complete',
                        'date_offset' => 20,
                        'description' => 'All development work finished'
                    ]
                ]
            ]
        ]);
    }

    public function task(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'category' => Template::CATEGORY_TASK,
            'template_data' => [
                'tasks' => [
                    [
                        'name' => 'Task Template',
                        'description' => 'Standard task template',
                        'duration_days' => 3,
                        'priority' => 'medium',
                        'estimated_hours' => 24,
                        'checklist' => [
                            'Review requirements',
                            'Implement solution',
                            'Test functionality',
                            'Document changes'
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function workflow(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'category' => Template::CATEGORY_WORKFLOW,
            'template_data' => [
                'workflow' => [
                    'steps' => [
                        [
                            'name' => 'Initiation',
                            'type' => 'start',
                            'assignee_role' => 'pm'
                        ],
                        [
                            'name' => 'Review',
                            'type' => 'approval',
                            'assignee_role' => 'admin'
                        ],
                        [
                            'name' => 'Execution',
                            'type' => 'task',
                            'assignee_role' => 'engineer'
                        ],
                        [
                            'name' => 'Completion',
                            'type' => 'end',
                            'assignee_role' => 'pm'
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function published(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => Template::STATUS_ACTIVE,
            'is_active' => true
        ]);
    }

    public function draft(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => Template::STATUS_DRAFT,
            'is_active' => true
        ]);
    }

    public function archived(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => Template::STATUS_ARCHIVED,
            'is_active' => false
        ]);
    }

    public function popular(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $this->faker->numberBetween(50, 500),
            'is_public' => true,
            'status' => Template::STATUS_ACTIVE
        ]);
    }

    private function generateTemplateData(): array
    {
        return [
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
            ],
            'milestones' => [
                [
                    'name' => 'Milestone 1',
                    'date_offset' => $this->faker->numberBetween(5, 20),
                    'description' => $this->faker->sentence()
                ]
            ]
        ];
    }
}
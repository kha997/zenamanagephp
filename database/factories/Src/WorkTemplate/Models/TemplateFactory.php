<?php declare(strict_types=1);

namespace Database\Factories\Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\WorkTemplate\Models\Template;

/**
 * Factory cho Template model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\WorkTemplate\Models\Template>
 */
class TemplateFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = Template::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $templateName = $this->faker->words(3, true) . ' Template';
        
        return [
            'template_name' => $templateName,
            'category' => $this->faker->randomElement(Template::CATEGORIES),
            'json_body' => $this->generateSampleJsonBody($templateName),
            'version' => 1,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * Tạo template không hoạt động
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Tạo template với category cụ thể
     */
    public function withCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
            'json_body' => $this->generateSampleJsonBody($attributes['template_name'], $category),
        ]);
    }

    /**
     * Tạo template với version cụ thể
     */
    public function withVersion(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * Tạo template với JSON body tùy chỉnh
     */
    public function withJsonBody(array $jsonBody): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => $jsonBody,
        ]);
    }

    /**
     * Tạo template với phases rỗng
     */
    public function withEmptyPhases(): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => [
                'template_name' => $attributes['template_name'],
                'phases' => []
            ],
        ]);
    }

    /**
     * Tạo template với cấu trúc phức tạp cho testing
     */
    public function withComplexStructure(): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => [
                'template_name' => $attributes['template_name'],
                'phases' => [
                    [
                        'name' => 'Planning Phase',
                        'tasks' => [
                            [
                                'name' => 'Requirements Analysis',
                                'duration_days' => 5,
                                'role' => 'Business Analyst',
                                'contract_value_percent' => 8.0,
                                'conditional_tag' => 'analysis_required',
                            ],
                            [
                                'name' => 'System Design',
                                'duration_days' => 7,
                                'role' => 'System Architect',
                                'contract_value_percent' => 12.0,
                            ],
                            [
                                'name' => 'Database Design',
                                'duration_days' => 3,
                                'role' => 'Database Designer',
                                'contract_value_percent' => 6.0,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Development Phase',
                        'tasks' => [
                            [
                                'name' => 'Backend Development',
                                'duration_days' => 15,
                                'role' => 'Backend Developer',
                                'contract_value_percent' => 25.0,
                            ],
                            [
                                'name' => 'Frontend Development',
                                'duration_days' => 12,
                                'role' => 'Frontend Developer',
                                'contract_value_percent' => 20.0,
                            ],
                            [
                                'name' => 'API Integration',
                                'duration_days' => 5,
                                'role' => 'Full Stack Developer',
                                'contract_value_percent' => 8.0,
                            ],
                        ],
                    ],
                    [
                        'name' => 'Testing Phase',
                        'tasks' => [
                            [
                                'name' => 'Unit Testing',
                                'duration_days' => 4,
                                'role' => 'QA Engineer',
                                'contract_value_percent' => 6.0,
                                'conditional_tag' => 'testing_required',
                            ],
                            [
                                'name' => 'Integration Testing',
                                'duration_days' => 6,
                                'role' => 'QA Engineer',
                                'contract_value_percent' => 9.0,
                            ],
                            [
                                'name' => 'User Acceptance Testing',
                                'duration_days' => 3,
                                'role' => 'QA Lead',
                                'contract_value_percent' => 6.0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Tạo sample JSON body cho template
     */
    private function generateSampleJsonBody(string $templateName, string $category = null): array
    {
        $phases = [];
        $phaseCount = $this->faker->numberBetween(2, 4);
        
        for ($i = 1; $i <= $phaseCount; $i++) {
            $tasks = [];
            $taskCount = $this->faker->numberBetween(2, 5);
            
            for ($j = 1; $j <= $taskCount; $j++) {
                $tasks[] = [
                    'name' => $this->faker->words(3, true),
                    'duration_days' => $this->faker->numberBetween(1, 10),
                    'role' => $this->faker->randomElement(['Engineer', 'Designer', 'QC Inspector', 'Project Manager']),
                    'contract_value_percent' => $this->faker->randomFloat(2, 5, 25),
                    'conditional_tag' => $this->faker->optional(0.3)->randomElement(['design_required', 'testing_required', 'inspection_required']),
                ];
            }
            
            $phases[] = [
                'name' => 'Phase ' . $i,
                'tasks' => $tasks
            ];
        }
        
        return [
            'template_name' => $templateName,
            'phases' => $phases
        ];
    }
}
<?php declare(strict_types=1);

namespace Database\Factories;

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
        return [
            'template_name' => $this->faker->words(3, true) . ' Template',
            'category' => $this->faker->randomElement(Template::CATEGORIES),
            'json_body' => $this->generateValidJsonBody(),
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
     * Tạo template với JSON body không hợp lệ
     */
    public function withInvalidJsonBody(): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => [
                'template_name' => 'Invalid Template',
                // Thiếu phases array
            ],
        ]);
    }

    /**
     * Tạo template với nhiều phases và tasks
     */
    public function withComplexStructure(): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => $this->generateComplexJsonBody(),
        ]);
    }

    /**
     * Tạo JSON body hợp lệ cho template
     */
    private function generateValidJsonBody(): array
    {
        return [
            'template_name' => $this->faker->words(3, true) . ' Template',
            'description' => $this->faker->sentence(),
            'phases' => [
                [
                    'name' => 'Phase 1: Planning',
                    'order' => 1,
                    'tasks' => [
                        [
                            'name' => 'Task 1.1: Requirements Analysis',
                            'duration_days' => 5,
                            'role' => 'Project Manager',
                            'contract_value_percent' => 10.0,
                            'dependencies' => [],
                            'conditional_tag' => null,
                        ],
                        [
                            'name' => 'Task 1.2: Design Review',
                            'duration_days' => 3,
                            'role' => 'Architect',
                            'contract_value_percent' => 8.0,
                            'dependencies' => ['1.1'],
                            'conditional_tag' => 'design_required',
                        ],
                    ],
                ],
                [
                    'name' => 'Phase 2: Implementation',
                    'order' => 2,
                    'tasks' => [
                        [
                            'name' => 'Task 2.1: Development',
                            'duration_days' => 15,
                            'role' => 'Developer',
                            'contract_value_percent' => 50.0,
                            'dependencies' => ['1.2'],
                            'conditional_tag' => null,
                        ],
                        [
                            'name' => 'Task 2.2: Testing',
                            'duration_days' => 7,
                            'role' => 'QA Engineer',
                            'contract_value_percent' => 20.0,
                            'dependencies' => ['2.1'],
                            'conditional_tag' => 'testing_required',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tạo JSON body phức tạp với nhiều phases và tasks
     */
    private function generateComplexJsonBody(): array
    {
        $phases = [];
        $phaseCount = $this->faker->numberBetween(3, 5);
        
        for ($i = 1; $i <= $phaseCount; $i++) {
            $tasks = [];
            $taskCount = $this->faker->numberBetween(2, 6);
            
            for ($j = 1; $j <= $taskCount; $j++) {
                $tasks[] = [
                    'name' => "Task {$i}.{$j}: " . $this->faker->words(3, true),
                    'duration_days' => $this->faker->numberBetween(1, 20),
                    'role' => $this->faker->randomElement(['Project Manager', 'Developer', 'Designer', 'QA Engineer', 'Architect']),
                    'contract_value_percent' => $this->faker->randomFloat(2, 1, 25),
                    'dependencies' => $j > 1 ? ["{$i}." . ($j - 1)] : [],
                    'conditional_tag' => $this->faker->optional(0.3)->randomElement(['design_required', 'testing_required', 'review_needed']),
                ];
            }
            
            $phases[] = [
                'name' => "Phase {$i}: " . $this->faker->words(2, true),
                'order' => $i,
                'tasks' => $tasks,
            ];
        }
        
        return [
            'template_name' => $this->faker->words(3, true) . ' Complex Template',
            'description' => $this->faker->paragraph(),
            'phases' => $phases,
        ];
    }
}
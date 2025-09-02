<?php declare(strict_types=1);

namespace Database\Factories\Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\WorkTemplate\Models\TemplateVersion;
use Src\WorkTemplate\Models\Template;

/**
 * Factory cho TemplateVersion model
 * 
 * Tạo dữ liệu giả cho testing TemplateVersion
 * Hỗ trợ các version khác nhau của template
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\WorkTemplate\Models\TemplateVersion>
 */
class TemplateVersionFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = TemplateVersion::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => Template::factory(),
            'version' => $this->faker->numberBetween(1, 10),
            'json_body' => $this->generateSampleJsonBody(),
            'note' => $this->faker->optional()->sentence(),
            'created_by' => null,
        ];
    }

    /**
     * Tạo version với note cụ thể
     */
    public function withNote(string $note): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note,
        ]);
    }

    /**
     * Tạo version đầu tiên (version 1)
     */
    public function firstVersion(): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => 1,
            'note' => 'Initial version',
        ]);
    }

    /**
     * Tạo version với template_id cụ thể
     */
    public function forTemplate(string $templateId): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => $templateId,
        ]);
    }

    /**
     * Tạo dữ liệu JSON mẫu cho template version
     *
     * @return array
     */
    private function generateSampleJsonBody(): array
    {
        $templateName = $this->faker->words(3, true) . ' Template';
        
        return [
            'template_name' => $templateName,
            'phases' => [
                [
                    'phase_name' => 'Phase 1: ' . $this->faker->words(2, true),
                    'order' => 1,
                    'tasks' => [
                        [
                            'task_name' => $this->faker->sentence(4),
                            'duration_days' => $this->faker->numberBetween(1, 30),
                            'order' => 1,
                            'conditional_tag' => null,
                        ],
                        [
                            'task_name' => $this->faker->sentence(4),
                            'duration_days' => $this->faker->numberBetween(1, 30),
                            'order' => 2,
                            'conditional_tag' => null,
                        ],
                    ],
                ],
                [
                    'phase_name' => 'Phase 2: ' . $this->faker->words(2, true),
                    'order' => 2,
                    'tasks' => [
                        [
                            'task_name' => $this->faker->sentence(4),
                            'duration_days' => $this->faker->numberBetween(1, 30),
                            'order' => 1,
                            'conditional_tag' => $this->faker->optional()->word(),
                        ],
                    ],
                ],
            ],
        ];
    }
}
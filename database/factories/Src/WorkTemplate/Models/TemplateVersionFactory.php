<?php declare(strict_types=1);

namespace Database\Factories\Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\WorkTemplate\Models\TemplateVersion;
use Src\WorkTemplate\Models\Template;

/**
 * Factory cho TemplateVersion model
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
            'version' => $this->faker->numberBetween(1, 5),
            'json_body' => $this->generateSampleJsonBody(),
            'note' => $this->faker->optional(0.7)->sentence(),
            'created_by' => null,
        ];
    }

    /**
     * Tạo version cho template cụ thể
     */
    public function forTemplate(string $templateId): static
    {
        return $this->state(fn (array $attributes) => [
            'template_id' => $templateId,
        ]);
    }

    /**
     * Tạo version với số version cụ thể
     */
    public function withVersion(int $version): static
    {
        return $this->state(fn (array $attributes) => [
            'version' => $version,
        ]);
    }

    /**
     * Tạo version với JSON body tùy chỉnh
     */
    public function withJsonBody(array $jsonBody): static
    {
        return $this->state(fn (array $attributes) => [
            'json_body' => $jsonBody,
        ]);
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
     * Tạo sample JSON body cho template version
     */
    private function generateSampleJsonBody(): array
    {
        $templateName = $this->faker->words(3, true) . ' Template';
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
<?php declare(strict_types=1);

namespace Database\Factories\Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\CoreProject\Models\Project;
use Src\WorkTemplate\Models\Template;

/**
 * Factory cho ProjectPhase model
 * 
 * Tạo dữ liệu giả cho testing ProjectPhase
 * Hỗ trợ các trạng thái và cấu hình khác nhau
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\WorkTemplate\Models\ProjectPhase>
 */
class ProjectPhaseFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = ProjectPhase::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'project_id' => Project::factory(),
            'name' => $this->faker->words(3, true),
            'order' => $this->faker->numberBetween(1, 10),
            'template_id' => null,
            'template_phase_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * State cho phase được tạo từ template
     * 
     * @param Template|null $template Template để liên kết
     * @return static
     */
    public function fromTemplate(Template $template = null): static
    {
        return $this->state(function (array $attributes) use ($template) {
            $template = $template ?? Template::factory()->create();
            
            return [
                'template_id' => $template->id,
                'template_phase_id' => $this->faker->uuid(),
            ];
        });
    }

    /**
     * State cho phase với order cụ thể
     * 
     * @param int $order Thứ tự của phase
     * @return static
     */
    public function withOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }

    /**
     * State cho phase thuộc project cụ thể
     * 
     * @param Project $project Project để liên kết
     * @return static
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * State cho phase đầu tiên (order = 1)
     * 
     * @return static
     */
    public function first(): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => 1,
            'name' => 'Planning Phase',
        ]);
    }

    /**
     * State cho phase cuối cùng
     * 
     * @return static
     */
    public function last(): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => 999,
            'name' => 'Completion Phase',
        ]);
    }
}

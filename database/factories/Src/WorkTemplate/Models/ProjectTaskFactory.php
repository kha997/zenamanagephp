<?php declare(strict_types=1);

namespace Database\Factories\Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\WorkTemplate\Models\ProjectTask;
use Src\CoreProject\Models\Project;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\Template;

/**
 * Factory cho ProjectTask model
 * 
 * Tạo dữ liệu giả cho testing ProjectTask
 * Hỗ trợ các trạng thái, conditional tags và cấu hình khác nhau
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\WorkTemplate\Models\ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     *
     * @var string
     */
    protected $model = ProjectTask::class;

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
            'phase_id' => ProjectPhase::factory(),
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(2),
            'duration_days' => $this->faker->numberBetween(1, 30),
            'progress_percent' => $this->faker->randomFloat(2, 0, 100),
            'status' => $this->faker->randomElement([
                ProjectTask::STATUS_PENDING,
                ProjectTask::STATUS_IN_PROGRESS,
                ProjectTask::STATUS_COMPLETED,
                ProjectTask::STATUS_ON_HOLD,
                ProjectTask::STATUS_CANCELLED,
            ]),
            'conditional_tag' => null,
            'is_hidden' => false,
            'template_id' => null,
            'template_task_id' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    /**
     * State cho task với conditional tag
     * 
     * @param string $tag Conditional tag
     * @param bool $isHidden Có ẩn task không
     * @return static
     */
    public function withConditionalTag(string $tag, bool $isHidden = true): static
    {
        return $this->state(fn (array $attributes) => [
            'conditional_tag' => $tag,
            'is_hidden' => $isHidden,
        ]);
    }

    /**
     * State cho task được tạo từ template
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
                'template_task_id' => $this->faker->uuid(),
            ];
        });
    }

    /**
     * State cho task với status cụ thể
     * 
     * @param string $status Status của task
     * @return static
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * State cho task completed
     * 
     * @return static
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectTask::STATUS_COMPLETED,
            'progress_percent' => 100.0,
        ]);
    }

    /**
     * State cho task in progress
     * 
     * @return static
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'progress_percent' => $this->faker->randomFloat(2, 10, 90),
        ]);
    }

    /**
     * State cho task pending
     * 
     * @return static
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectTask::STATUS_PENDING,
            'progress_percent' => 0.0,
        ]);
    }

    /**
     * State cho task thuộc project cụ thể
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
     * State cho task thuộc phase cụ thể
     * 
     * @param ProjectPhase $phase Phase để liên kết
     * @return static
     */
    public function forPhase(ProjectPhase $phase): static
    {
        return $this->state(fn (array $attributes) => [
            'phase_id' => $phase->id,
            'project_id' => $phase->project_id,
        ]);
    }

    /**
     * State cho task ẩn (hidden)
     * 
     * @return static
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * State cho task visible
     * 
     * @return static
     */
    public function visible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => false,
        ]);
    }

    /**
     * State cho task với duration cụ thể
     * 
     * @param int $days Số ngày duration
     * @return static
     */
    public function withDuration(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_days' => $days,
        ]);
    }

    /**
     * State cho task với progress cụ thể
     * 
     * @param float $progress Progress percent (0-100)
     * @return static
     */
    public function withProgress(float $progress): static
    {
        return $this->state(fn (array $attributes) => [
            'progress_percent' => $progress,
        ]);
    }
}

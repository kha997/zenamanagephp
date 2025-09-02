<?php declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\InteractionLogs\Models\InteractionLog;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use App\Models\User;

/**
 * Factory cho InteractionLog model
 * 
 * Tạo dữ liệu giả cho testing InteractionLog
 * Hỗ trợ các loại tương tác và mức độ hiển thị khác nhau
 */
class InteractionLogFactory extends Factory
{
    /**
     * Model tương ứng với factory này
     */
    protected $model = InteractionLog::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'linked_task_id' => $this->faker->boolean(30) ? Task::factory() : null,
            'type' => $this->faker->randomElement(InteractionLog::VALID_TYPES),
            'description' => $this->faker->paragraph(2),
            'tag_path' => $this->faker->optional(0.7)->randomElement([
                'Material/Flooring/Granite',
                'Design/Architecture/Blueprint',
                'Construction/Foundation/Concrete',
                'QC/Inspection/Safety',
                'Client/Feedback/Requirements'
            ]),
            'visibility' => $this->faker->randomElement(InteractionLog::VALID_VISIBILITIES),
            'client_approved' => $this->faker->boolean(60),
            'created_by' => User::factory(),
        ];
    }

    /**
     * State cho interaction log loại call
     */
    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InteractionLog::TYPE_CALL,
            'description' => 'Cuộc gọi với ' . $this->faker->name . ' về ' . $this->faker->sentence(5),
        ]);
    }

    /**
     * State cho interaction log loại email
     */
    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InteractionLog::TYPE_EMAIL,
            'description' => 'Email trao đổi về ' . $this->faker->sentence(5),
        ]);
    }

    /**
     * State cho interaction log loại meeting
     */
    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InteractionLog::TYPE_MEETING,
            'description' => 'Cuộc họp thảo luận về ' . $this->faker->sentence(5),
        ]);
    }

    /**
     * State cho interaction log loại note
     */
    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InteractionLog::TYPE_NOTE,
            'description' => 'Ghi chú: ' . $this->faker->paragraph(1),
        ]);
    }

    /**
     * State cho interaction log loại feedback
     */
    public function feedback(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => InteractionLog::TYPE_FEEDBACK,
            'description' => 'Phản hồi từ khách hàng: ' . $this->faker->paragraph(1),
        ]);
    }

    /**
     * State cho log hiển thị internal
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => InteractionLog::VISIBILITY_INTERNAL,
            'client_approved' => false,
        ]);
    }

    /**
     * State cho log hiển thị client (đã approve)
     */
    public function clientVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => InteractionLog::VISIBILITY_CLIENT,
            'client_approved' => true,
        ]);
    }

    /**
     * State cho log hiển thị client (chưa approve)
     */
    public function clientPending(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => InteractionLog::VISIBILITY_CLIENT,
            'client_approved' => false,
        ]);
    }

    /**
     * State cho log với project cụ thể
     */
    public function forProject(string $projectId): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $projectId,
        ]);
    }

    /**
     * State cho log với task cụ thể
     */
    public function forTask(string $taskId): static
    {
        return $this->state(fn (array $attributes) => [
            'linked_task_id' => $taskId,
        ]);
    }

    /**
     * State cho log với tag path cụ thể
     */
    public function withTagPath(string $tagPath): static
    {
        return $this->state(fn (array $attributes) => [
            'tag_path' => $tagPath,
        ]);
    }

    /**
     * State cho log được tạo bởi user cụ thể
     */
    public function createdBy(string $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $userId,
        ]);
    }
}
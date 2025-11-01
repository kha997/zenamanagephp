<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaskComment>
 */
class TaskCommentFactory extends Factory
{
    protected $model = TaskComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'content' => $this->faker->paragraph(),
            'type' => $this->faker->randomElement(TaskComment::VALID_TYPES),
            'metadata' => null,
            'parent_id' => null,
            'is_internal' => false, // Default to public comments
            'is_pinned' => false, // Default to unpinned comments
        ];
    }

    /**
     * Create a regular comment
     */
    public function comment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskComment::TYPE_COMMENT,
            'is_internal' => false,
        ]);
    }

    /**
     * Create a status change comment
     */
    public function statusChange(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskComment::TYPE_STATUS_CHANGE,
            'content' => 'Status changed from pending to in_progress',
            'metadata' => [
                'old_status' => 'pending',
                'new_status' => 'in_progress',
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Create an assignment comment
     */
    public function assignment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskComment::TYPE_ASSIGNMENT,
            'content' => 'Task assigned to John Doe',
            'metadata' => [
                'old_assignee_id' => null,
                'new_assignee_id' => User::factory(),
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Create a mention comment
     */
    public function mention(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskComment::TYPE_MENTION,
            'content' => 'Hey @johndoe, can you review this?',
            'metadata' => [
                'mentioned_user_id' => User::factory(),
            ],
            'is_internal' => false,
        ]);
    }

    /**
     * Create a system comment
     */
    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskComment::TYPE_SYSTEM,
            'content' => 'Task created automatically',
            'metadata' => [
                'action' => 'created',
                'source' => 'system',
            ],
            'is_internal' => true,
        ]);
    }

    /**
     * Create a pinned comment
     */
    public function pinned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
        ]);
    }

    /**
     * Create an internal comment
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    /**
     * Create a reply comment
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => TaskComment::factory(),
        ]);
    }
}

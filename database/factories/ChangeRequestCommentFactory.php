<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChangeRequestComment;
use App\Models\ZenaChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangeRequestComment>
 */
class ChangeRequestCommentFactory extends Factory
{
    protected $model = ChangeRequestComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->regexify('[0-9A-Za-z]{26}'),
            'change_request_id' => ZenaChangeRequest::factory(),
            'user_id' => User::factory(),
            'comment' => $this->faker->paragraphs(2, true),
            'parent_id' => null,
            'is_internal' => $this->faker->boolean(30),
        ];
    }

    /**
     * Indicate that the comment is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => true,
        ]);
    }

    /**
     * Indicate that the comment is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }

    /**
     * Indicate that the comment is a reply.
     */
    public function reply(): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => ChangeRequestComment::factory(),
        ]);
    }
}

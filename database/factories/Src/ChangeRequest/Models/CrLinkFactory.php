<?php declare(strict_types=1);

namespace Database\Factories\Src\ChangeRequest\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\ChangeRequest\Models\CrLink;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\CoreProject\Models\Task;
use Src\DocumentManagement\Models\Document;
use Src\CoreProject\Models\Component;

/**
 * Factory cho CrLink model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\ChangeRequest\Models\CrLink>
 */
class CrLinkFactory extends Factory
{
    protected $model = CrLink::class;

    public function definition(): array
    {
        $linkableTypes = [Task::class, Document::class, Component::class];
        $linkableType = $this->faker->randomElement($linkableTypes);
        
        return [
            'change_request_id' => ChangeRequest::factory(),
            'linkable_type' => $linkableType,
            'linkable_id' => $linkableType::factory(),
            'link_type' => $this->faker->randomElement(CrLink::VALID_LINK_TYPES),
            'description' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * State: Linked to task
     */
    public function linkedToTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'linkable_type' => Task::class,
            'linkable_id' => Task::factory(),
        ]);
    }

    /**
     * State: Impact link
     */
    public function impact(): static
    {
        return $this->state(fn (array $attributes) => [
            'link_type' => CrLink::LINK_TYPE_IMPACT,
        ]);
    }
}
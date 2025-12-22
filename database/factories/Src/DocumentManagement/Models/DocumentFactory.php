<?php declare(strict_types=1);

namespace Database\Factories\Src\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\DocumentManagement\Models\Document;
use Src\CoreProject\Models\Project;
use App\Models\User;

/**
 * Factory cho Document model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\DocumentManagement\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => $this->faker->sentence(3),
            'original_name' => $this->faker->word() . '.pdf',
            'description' => $this->faker->paragraph(2),
            'file_path' => 'documents/test/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_type' => 'application/pdf',
            'mime_type' => 'application/pdf',
            'file_hash' => $this->faker->sha256(),
            'category' => $this->faker->randomElement(['technical', 'business', 'legal', 'other']),
            'status' => 'active',
            'tenant_id' => 1, // Will be set by test
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * State: Client visible document
     */
    public function clientVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Document::VISIBILITY_CLIENT,
            'client_approved' => true,
        ]);
    }

    /**
     * State: Internal document
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => Document::VISIBILITY_INTERNAL,
        ]);
    }

    /**
     * State: Linked to task
     */
    public function linkedToTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'linked_entity_type' => Document::ENTITY_TYPE_TASK,
        ]);
    }
}
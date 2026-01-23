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
            'uploaded_by' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(2),
            'linked_entity_type' => $this->faker->randomElement(Document::VALID_ENTITY_TYPES),
            'linked_entity_id' => $this->faker->uuid(),
            'current_version_id' => null, // Will be set after DocumentVersion is created
            'tags' => $this->faker->randomElements(['contract', 'design', 'specification', 'report'], $this->faker->numberBetween(0, 2)),
            'visibility' => $this->faker->randomElement(Document::VALID_VISIBILITY),
            'client_approved' => $this->faker->boolean(30),
            'original_name' => $this->faker->word() . '.pdf',
            'file_path' => 'documents/test-file.pdf',
            'file_type' => 'application/pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 5000),
            'file_hash' => $this->faker->md5,
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
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

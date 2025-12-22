<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'tenant_id' => null, // Will be provided by test
            'project_id' => null, // Will be provided by test
            'name' => $this->faker->words(3, true),
            'original_name' => $this->faker->words(3, true) . '.pdf',
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_hash' => $this->faker->sha256(),
            'category' => $this->faker->randomElement(['general', 'drawing', 'specification', 'contract', 'report']),
            'description' => $this->faker->sentence(),
            'metadata' => json_encode(['author' => $this->faker->name(), 'tags' => $this->faker->words(3)]),
            'status' => $this->faker->randomElement(['draft', 'review', 'approved', 'published']),
            'version' => $this->faker->numberBetween(1, 5),
            'is_current_version' => true,
            'parent_document_id' => null,
            // uploaded_by is required (NOT NULL FK) - must be provided explicitly
            // Use forProjectAndTenant() helper or pass uploaded_by in create() call
            'uploaded_by' => null,
        ];
    }

    /**
     * Set the document to belong to a specific project and tenant
     * This ensures all foreign keys are properly set
     */
    public function forProjectAndTenant($project, $user): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
        ]);
    }

}

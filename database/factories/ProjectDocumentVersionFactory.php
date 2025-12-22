<?php

namespace Database\Factories;

use App\Models\ProjectDocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectDocumentVersion>
 */
class ProjectDocumentVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ProjectDocumentVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => null, // Will be provided by test
            'project_id' => null, // Will be provided by test
            'tenant_id' => null, // Will be provided by test
            'version_number' => $this->faker->numberBetween(1, 10),
            'name' => $this->faker->words(3, true),
            'original_name' => $this->faker->words(3, true) . '.pdf',
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_hash' => $this->faker->sha256(),
            'uploaded_by' => null, // Will be provided by test
        ];
    }
}

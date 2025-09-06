<?php declare(strict_types=1);

namespace Database\Factories\Src\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\DocumentManagement\Models\DocumentVersion;
use Src\DocumentManagement\Models\Document;
use App\Models\User;

/**
 * Factory cho DocumentVersion model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\DocumentManagement\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => $this->faker->numberBetween(1, 10),
            'file_path' => 'documents/' . $this->faker->uuid() . '.pdf',
            'storage_driver' => $this->faker->randomElement(DocumentVersion::VALID_STORAGE_DRIVERS),
            'comment' => $this->faker->optional(0.7)->sentence(),
            'metadata' => [
                'file_size' => $this->faker->numberBetween(1024, 10485760), // 1KB to 10MB
                'mime_type' => $this->faker->randomElement(['application/pdf', 'image/jpeg', 'application/msword']),
                'original_name' => $this->faker->word() . '.pdf',
            ],
            'created_by' => User::factory(),
            'reverted_from_version_number' => null,
        ];
    }

    /**
     * State: First version
     */
    public function firstVersion(): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => 1,
            'comment' => 'Initial version',
        ]);
    }

    /**
     * State: Reverted version
     */
    public function reverted(int $fromVersion): static
    {
        return $this->state(fn (array $attributes) => [
            'reverted_from_version_number' => $fromVersion,
            'comment' => "Reverted from version {$fromVersion}",
        ]);
    }

    /**
     * State: S3 storage
     */
    public function s3Storage(): static
    {
        return $this->state(fn (array $attributes) => [
            'storage_driver' => DocumentVersion::STORAGE_DRIVER_S3,
        ]);
    }
}
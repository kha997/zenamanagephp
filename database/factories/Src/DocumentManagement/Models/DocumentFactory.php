<?php declare(strict_types=1);

namespace Database\Factories\Src\DocumentManagement\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\DocumentManagement\Models\Document;
use Src\CoreProject\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Str;

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
            'id' => Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'project_id' => Project::factory(),
            'uploaded_by' => User::factory(),
            'name' => $this->faker->sentence(3),
            'original_name' => Str::slug($this->faker->word) . '.pdf',
            'file_path' => 'documents/' . Str::random(10) . '/' . Str::slug($this->faker->word) . '.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 100000),
            'file_hash' => Str::ulid(),
            'category' => 'drawing',
            'description' => $this->faker->paragraph(),
            'metadata' => json_encode(['document_type' => 'drawing']),
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

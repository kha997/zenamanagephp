<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->for($tenant, 'tenant')->create();
        $uploader = User::factory()->for($tenant, 'tenant')->create();
        $title = $this->faker->words(3, true);

        return [
            'id' => Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'uploaded_by' => $uploader->id,
            'name' => $title,
            'original_name' => $title . '.pdf',
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_type' => $this->faker->randomElement(['document', 'drawing', 'spec']),
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_hash' => $this->faker->md5,
            'category' => $this->faker->randomElement(['general', 'drawing', 'spec']),
            'description' => $this->faker->sentence(),
            'metadata' => [],
            'status' => $this->faker->randomElement(['active', 'archived']),
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
        ];
    }
}

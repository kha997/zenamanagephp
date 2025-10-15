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
        // If tenant_id is provided in attributes, use it; otherwise create new tenant
        $tenantId = $this->faker->optional(0.1)->passthrough($this->definition['tenant_id'] ?? null);
        
        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);
            $user = \App\Models\User::factory()->forTenant($tenantId)->create();
        } else {
            $tenant = Tenant::factory()->create();
            $user = User::factory()->forTenant($tenant->id)->create();
            $tenantId = $tenant->id;
        }
        
        return [
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenantId,
            'project_id' => null, // Có thể null
            'name' => $this->faker->words(3, true),
            'original_name' => $this->faker->words(3, true) . '.pdf',
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_hash' => $this->faker->sha256(),
            'category' => $this->faker->randomElement(['general', 'drawing', 'specification', 'contract']),
            'description' => $this->faker->sentence(),
            'metadata' => json_encode(['author' => $this->faker->name(), 'tags' => $this->faker->words(3)]),
            'status' => $this->faker->randomElement(['draft', 'review', 'approved', 'published']),
            'version' => $this->faker->numberBetween(1, 5),
            'is_current_version' => true,
            'parent_document_id' => null,
            'uploaded_by' => $user->id,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

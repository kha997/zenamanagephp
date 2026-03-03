<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliverableTemplateVersion>
 */
class DeliverableTemplateVersionFactory extends Factory
{
    protected $model = DeliverableTemplateVersion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'deliverable_template_id' => DeliverableTemplate::factory(),
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => 'deliverable-templates/' . $this->faker->uuid() . '/draft/source.html',
            'checksum_sha256' => hash('sha256', $this->faker->text(32)),
            'mime' => 'text/html',
            'size' => 0,
            'placeholders_spec_json' => [
                'schema_version' => '1.0.0',
                'placeholders' => [],
            ],
            'published_at' => null,
            'published_by' => null,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}

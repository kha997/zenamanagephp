<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportDocumentation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupportDocumentationFactory extends Factory
{
    protected $model = SupportDocumentation::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $author = User::factory()->state(['tenant_id' => $tenant->id])->create();
        $title = $this->faker->sentence(5);

        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::lower(Str::random(5)),
            'content' => $this->faker->paragraphs(3, true),
            'category' => $this->faker->randomElement(['getting_started', 'feature_guide', 'faq', 'api']),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'tags' => $this->faker->randomElement(['test,document', 'guides,api', 'faq']),
            'author_id' => $author->id,
        ];
    }
}

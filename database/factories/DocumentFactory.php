<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
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
            'id' => \Illuminate\Support\Str::ulid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => $this->faker->randomElement(['draft', 'review', 'approved', 'published']),
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}

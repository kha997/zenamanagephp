<?php

namespace Database\Factories;

use App\Models\PerformanceMetric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class PerformanceMetricFactory extends Factory
{
    protected $model = PerformanceMetric::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'tenant_id' => null,
            'metric_name' => $this->faker->word . '.' . $this->faker->word,
            'metric_value' => $this->faker->randomFloat(4, 0, 1_000_000),
            'metric_unit' => 'milliseconds',
            'category' => $this->faker->randomElement(['system', 'application', 'database']),
            'recorded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'metadata' => ['source' => 'factory'],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

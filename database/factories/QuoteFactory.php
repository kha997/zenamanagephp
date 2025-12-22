<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalAmount = $this->faker->randomFloat(2, 1000, 50000);
        $taxRate = $this->faker->randomFloat(2, 0, 15);
        $taxAmount = $totalAmount * ($taxRate / 100);
        $discountAmount = $this->faker->randomFloat(2, 0, $totalAmount * 0.1);
        $finalAmount = $totalAmount + $taxAmount - $discountAmount;

        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'project_id' => null, // Optional, will be set when quote is accepted
            'type' => $this->faker->randomElement(['design', 'construction']),
            'status' => $this->faker->randomElement(['draft', 'sent', 'viewed', 'accepted', 'rejected', 'expired']),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'total_amount' => $totalAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'line_items' => [
                [
                    'description' => 'Design Services',
                    'quantity' => $this->faker->numberBetween(1, 10),
                    'unit_price' => $this->faker->randomFloat(2, 100, 1000),
                    'total' => $this->faker->randomFloat(2, 500, 5000),
                ],
                [
                    'description' => 'Construction Materials',
                    'quantity' => $this->faker->numberBetween(1, 50),
                    'unit_price' => $this->faker->randomFloat(2, 50, 500),
                    'total' => $this->faker->randomFloat(2, 1000, 10000),
                ],
            ],
            'terms_conditions' => [
                'payment_terms' => $this->faker->randomElement(['Net 30', 'Net 15', 'Due on Receipt', '50% Down, 50% on Completion']),
                'warranty' => $this->faker->randomElement(['1 Year', '2 Years', '5 Years']),
                'delivery_time' => $this->faker->randomElement(['2-4 weeks', '4-6 weeks', '6-8 weeks', '8-12 weeks']),
            ],
            'valid_until' => $this->faker->dateTimeBetween('+1 week', '+1 month'),
            'sent_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'viewed_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 month', 'now'),
            'accepted_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 month', 'now'),
            'rejected_at' => $this->faker->optional(0.2)->dateTimeBetween('-1 month', 'now'),
            'rejection_reason' => $this->faker->optional(0.2)->sentence(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the quote is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'sent_at' => null,
            'viewed_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Indicate that the quote has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'viewed_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Indicate that the quote has been viewed.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'viewed',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'viewed_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Indicate that the quote has been accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'viewed_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'accepted_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejected_at' => null,
            'project_id' => Project::factory(),
        ]);
    }

    /**
     * Indicate that the quote has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'sent_at' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'viewed_at' => $this->faker->dateTimeBetween('-1 week', '-1 day'),
            'accepted_at' => null,
            'rejected_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the quote has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'valid_until' => $this->faker->dateTimeBetween('-1 month', '-1 week'),
            'sent_at' => $this->faker->dateTimeBetween('-2 months', '-1 month'),
            'viewed_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', '-1 week'),
            'accepted_at' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Indicate that the quote is for design services.
     */
    public function design(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'design',
        ]);
    }

    /**
     * Indicate that the quote is for construction services.
     */
    public function construction(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'construction',
        ]);
    }
}

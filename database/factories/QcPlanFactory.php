<?php

namespace Database\Factories;

use App\Models\QcPlan;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QcPlan>
 */
class QcPlanFactory extends Factory
{
    protected $model = QcPlan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['draft', 'active', 'completed', 'cancelled'];
        
        return [
            'project_id' => Project::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement($statuses),
            'start_date' => $this->faker->dateTimeBetween('-30 days', '+30 days'),
            'end_date' => $this->faker->dateTimeBetween('+30 days', '+90 days'),
            'created_by' => User::factory(),
            'checklist_items' => $this->generateChecklistItems(),
        ];
    }

    /**
     * Generate realistic checklist items
     */
    private function generateChecklistItems(): array
    {
        $checklistTemplates = [
            [
                'item' => 'Foundation inspection',
                'description' => 'Check foundation dimensions and concrete quality',
                'required' => true,
                'category' => 'Structural'
            ],
            [
                'item' => 'Steel reinforcement check',
                'description' => 'Verify steel placement and spacing',
                'required' => true,
                'category' => 'Structural'
            ],
            [
                'item' => 'Concrete strength test',
                'description' => 'Perform compressive strength test',
                'required' => true,
                'category' => 'Materials'
            ],
            [
                'item' => 'Electrical rough-in',
                'description' => 'Check electrical conduit and wiring',
                'required' => true,
                'category' => 'Electrical'
            ],
            [
                'item' => 'Plumbing rough-in',
                'description' => 'Verify plumbing lines and connections',
                'required' => true,
                'category' => 'Plumbing'
            ],
            [
                'item' => 'HVAC installation',
                'description' => 'Check HVAC system installation',
                'required' => false,
                'category' => 'Mechanical'
            ],
            [
                'item' => 'Fire safety systems',
                'description' => 'Verify fire sprinkler and alarm systems',
                'required' => true,
                'category' => 'Safety'
            ],
            [
                'item' => 'Insulation inspection',
                'description' => 'Check insulation installation and R-value',
                'required' => true,
                'category' => 'Thermal'
            ]
        ];

        return $this->faker->randomElements($checklistTemplates, $this->faker->numberBetween(3, 6));
    }

    /**
     * Indicate that the QC plan is draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'start_date' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'end_date' => $this->faker->dateTimeBetween('+30 days', '+90 days'),
        ]);
    }

    /**
     * Indicate that the QC plan is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'start_date' => $this->faker->dateTimeBetween('-10 days', 'now'),
            'end_date' => $this->faker->dateTimeBetween('+20 days', '+60 days'),
        ]);
    }

    /**
     * Indicate that the QC plan is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'start_date' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
            'end_date' => $this->faker->dateTimeBetween('-10 days', 'now'),
        ]);
    }

    /**
     * Indicate that the QC plan is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'start_date' => $this->faker->dateTimeBetween('-30 days', '-10 days'),
            'end_date' => $this->faker->dateTimeBetween('-10 days', 'now'),
        ]);
    }

    /**
     * Indicate that the QC plan is for structural work.
     */
    public function structural(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Structural QC Plan - ' . $this->faker->words(2, true),
            'description' => 'Quality control plan for structural elements including foundation, steel, and concrete work.',
            'checklist_items' => [
                [
                    'item' => 'Foundation inspection',
                    'description' => 'Check foundation dimensions and concrete quality',
                    'required' => true,
                    'category' => 'Structural'
                ],
                [
                    'item' => 'Steel reinforcement check',
                    'description' => 'Verify steel placement and spacing',
                    'required' => true,
                    'category' => 'Structural'
                ],
                [
                    'item' => 'Concrete strength test',
                    'description' => 'Perform compressive strength test',
                    'required' => true,
                    'category' => 'Materials'
                ]
            ]
        ]);
    }

    /**
     * Indicate that the QC plan is for MEP work.
     */
    public function mep(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'MEP QC Plan - ' . $this->faker->words(2, true),
            'description' => 'Quality control plan for mechanical, electrical, and plumbing systems.',
            'checklist_items' => [
                [
                    'item' => 'Electrical rough-in',
                    'description' => 'Check electrical conduit and wiring',
                    'required' => true,
                    'category' => 'Electrical'
                ],
                [
                    'item' => 'Plumbing rough-in',
                    'description' => 'Verify plumbing lines and connections',
                    'required' => true,
                    'category' => 'Plumbing'
                ],
                [
                    'item' => 'HVAC installation',
                    'description' => 'Check HVAC system installation',
                    'required' => true,
                    'category' => 'Mechanical'
                ]
            ]
        ]);
    }
}
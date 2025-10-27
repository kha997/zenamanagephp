<?php

namespace Database\Factories;

use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QcInspection>
 */
class QcInspectionFactory extends Factory
{
    protected $model = QcInspection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['scheduled', 'in_progress', 'completed', 'failed', 'cancelled'];
        
        return [
            'qc_plan_id' => QcPlan::factory(),
            'tenant_id' => Tenant::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement($statuses),
            'inspection_date' => $this->faker->dateTimeBetween('-30 days', '+30 days'),
            'inspector_id' => User::factory(),
            'findings' => $this->faker->optional(0.7)->paragraph(2),
            'recommendations' => $this->faker->optional(0.6)->paragraph(2),
            'checklist_results' => $this->generateChecklistResults(),
            'photos' => $this->generatePhotos(),
        ];
    }

    /**
     * Generate realistic checklist results
     */
    private function generateChecklistResults(): array
    {
        $results = [];
        $items = [
            'Foundation inspection',
            'Steel reinforcement check',
            'Concrete strength test',
            'Electrical rough-in',
            'Plumbing rough-in',
            'HVAC installation',
            'Fire safety systems',
            'Insulation inspection'
        ];

        $selectedItems = $this->faker->randomElements($items, $this->faker->numberBetween(3, 6));
        
        foreach ($selectedItems as $item) {
            $results[] = [
                'item' => $item,
                'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                'notes' => $this->faker->optional(0.6)->sentence(2),
                'inspected_by' => $this->faker->name(),
                'inspected_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
            ];
        }

        return $results;
    }

    /**
     * Generate realistic photo references
     */
    private function generatePhotos(): array
    {
        if ($this->faker->boolean(0.4)) {
            return [];
        }

        $photoTypes = [
            'foundation_001.jpg',
            'steel_reinforcement_002.jpg',
            'concrete_test_003.jpg',
            'electrical_rough_004.jpg',
            'plumbing_lines_005.jpg',
            'hvac_install_006.jpg',
            'fire_system_007.jpg',
            'insulation_check_008.jpg'
        ];

        return $this->faker->randomElements($photoTypes, $this->faker->numberBetween(1, 4));
    }

    /**
     * Indicate that the inspection is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'inspection_date' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'findings' => null,
            'recommendations' => null,
            'checklist_results' => [],
            'photos' => [],
        ]);
    }

    /**
     * Indicate that the inspection is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'inspection_date' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'findings' => $this->faker->optional(0.3)->paragraph(1),
            'recommendations' => null,
            'checklist_results' => $this->generatePartialChecklistResults(),
            'photos' => $this->faker->optional(0.2)->randomElements([
                'in_progress_001.jpg',
                'in_progress_002.jpg'
            ], $this->faker->numberBetween(1, 2)),
        ]);
    }

    /**
     * Indicate that the inspection is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'inspection_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'findings' => $this->faker->paragraph(2),
            'recommendations' => $this->faker->paragraph(2),
            'checklist_results' => $this->generateCompleteChecklistResults(),
            'photos' => $this->generatePhotos(),
        ]);
    }

    /**
     * Indicate that the inspection failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'inspection_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            'findings' => $this->faker->paragraph(2) . ' FAILED: ' . $this->faker->sentence(1),
            'recommendations' => 'Immediate corrective action required: ' . $this->faker->sentence(2),
            'checklist_results' => $this->generateFailedChecklistResults(),
            'photos' => $this->generatePhotos(),
        ]);
    }

    /**
     * Generate partial checklist results (for in-progress inspections)
     */
    private function generatePartialChecklistResults(): array
    {
        $items = [
            'Foundation inspection',
            'Steel reinforcement check',
            'Concrete strength test'
        ];

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'item' => $item,
                'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                'notes' => $this->faker->optional(0.6)->sentence(2),
                'inspected_by' => $this->faker->name(),
                'inspected_at' => $this->faker->dateTimeBetween('-5 days', 'now'),
            ];
        }

        return $results;
    }

    /**
     * Generate complete checklist results (for completed inspections)
     */
    private function generateCompleteChecklistResults(): array
    {
        $items = [
            'Foundation inspection',
            'Steel reinforcement check',
            'Concrete strength test',
            'Electrical rough-in',
            'Plumbing rough-in',
            'HVAC installation',
            'Fire safety systems',
            'Insulation inspection'
        ];

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'item' => $item,
                'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                'notes' => $this->faker->optional(0.8)->sentence(2),
                'inspected_by' => $this->faker->name(),
                'inspected_at' => $this->faker->dateTimeBetween('-10 days', '-1 day'),
            ];
        }

        return $results;
    }

    /**
     * Generate failed checklist results (for failed inspections)
     */
    private function generateFailedChecklistResults(): array
    {
        $items = [
            'Foundation inspection',
            'Steel reinforcement check',
            'Concrete strength test',
            'Electrical rough-in',
            'Plumbing rough-in'
        ];

        $results = [];
        foreach ($items as $item) {
            $results[] = [
                'item' => $item,
                'status' => $this->faker->randomElement(['pass', 'fail']),
                'notes' => $this->faker->sentence(2),
                'inspected_by' => $this->faker->name(),
                'inspected_at' => $this->faker->dateTimeBetween('-10 days', '-1 day'),
            ];
        }

        return $results;
    }

    /**
     * Indicate that the inspection is for structural work.
     */
    public function structural(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => 'Structural Inspection - ' . $this->faker->words(2, true),
            'description' => 'Quality inspection for structural elements including foundation, steel, and concrete work.',
            'checklist_results' => [
                [
                    'item' => 'Foundation inspection',
                    'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                    'notes' => $this->faker->optional(0.8)->sentence(2),
                    'inspected_by' => $this->faker->name(),
                    'inspected_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
                ],
                [
                    'item' => 'Steel reinforcement check',
                    'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                    'notes' => $this->faker->optional(0.8)->sentence(2),
                    'inspected_by' => $this->faker->name(),
                    'inspected_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
                ],
                [
                    'item' => 'Concrete strength test',
                    'status' => $this->faker->randomElement(['pass', 'fail', 'conditional']),
                    'notes' => $this->faker->optional(0.8)->sentence(2),
                    'inspected_by' => $this->faker->name(),
                    'inspected_at' => $this->faker->dateTimeBetween('-10 days', 'now'),
                ]
            ]
        ]);
    }
}
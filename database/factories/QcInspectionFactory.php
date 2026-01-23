<?php

namespace Database\Factories;

use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QcInspectionFactory extends Factory
{
    protected $model = QcInspection::class;

    public function definition(): array
    {
        $qcPlan = QcPlan::factory()->create();

        $status = $this->faker->randomElement(['scheduled', 'in_progress', 'completed']);
        $scheduledAt = now()->subDays(2);
        $conductedAt = null;
        $completedAt = null;

        if ($status === 'scheduled') {
            $scheduledAt = now()->subDay();
        } elseif ($status === 'in_progress') {
            $conductedAt = now()->subHours(6);
        } elseif ($status === 'completed') {
            $conductedAt = now()->subDays(1);
            $completedAt = now();
        }

        return [
            'qc_plan_id' => $qcPlan->id,
            'tenant_id' => $qcPlan->tenant_id,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'status' => $status,
            'inspection_date' => $this->faker->dateTimeBetween('-1 week', '+1 week'),
            'scheduled_at' => $scheduledAt,
            'conducted_at' => $conductedAt,
            'completed_at' => $completedAt,
            'inspector_id' => User::factory()->create()->id,
            'findings' => $this->faker->paragraph(),
            'recommendations' => $this->faker->sentence(),
            'checklist_results' => [
                [
                    'item' => 'Sample item',
                    'result' => 'PASS',
                    'actual_value' => 'OK',
                    'notes' => 'Auto-generated result',
                ],
            ],
            'photos' => [
                [
                    'filename' => 'inspection.jpg',
                    'path' => 'qc_inspections/inspection.jpg',
                ],
            ],
        ];
    }
}

<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * AecIntl2025Seeder
 * 
 * Creates sample WBS template set for AEC International projects.
 * Includes phases, disciplines, tasks, dependencies, and presets.
 */
class AecIntl2025Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first super-admin user for created_by
        $admin = User::whereHas('roles', function ($query) {
            $query->where('name', 'super_admin');
        })->first();

        if (!$admin) {
            $this->command->warn('No super-admin user found. Skipping template seeder.');
            return;
        }

        DB::transaction(function () use ($admin) {
            // Create template set
            $set = TemplateSet::create([
                'code' => 'WBS-AEC-INTL',
                'name' => 'WBS AEC International',
                'description' => 'Standard WBS template for international AEC projects',
                'version' => '2025.1',
                'is_active' => true,
                'is_global' => true,
                'created_by' => $admin->id,
                'tenant_id' => null, // Global template
            ]);

            // Create phases
            $phases = [
                ['code' => 'CONCEPT', 'name' => 'Concept Design', 'order' => 1],
                ['code' => 'DESIGN', 'name' => 'Design Development', 'order' => 2],
                ['code' => 'CONSTRUCTION', 'name' => 'Construction', 'order' => 3],
                ['code' => 'QC', 'name' => 'Quality Control', 'order' => 4],
            ];

            $phaseMap = [];
            foreach ($phases as $phaseData) {
                $phase = TemplatePhase::create([
                    'set_id' => $set->id,
                    'code' => $phaseData['code'],
                    'name' => $phaseData['name'],
                    'order_index' => $phaseData['order'],
                ]);
                $phaseMap[$phaseData['code']] = $phase->id;
            }

            // Create disciplines
            $disciplines = [
                ['code' => 'ARC', 'name' => 'Architecture', 'color' => '#1E88E5', 'order' => 1],
                ['code' => 'MEP', 'name' => 'MEP Engineering', 'color' => '#43A047', 'order' => 2],
                ['code' => 'STR', 'name' => 'Structural Engineering', 'color' => '#E53935', 'order' => 3],
                ['code' => 'LND', 'name' => 'Landscape', 'color' => '#8BC34A', 'order' => 4],
            ];

            $disciplineMap = [];
            foreach ($disciplines as $disciplineData) {
                $discipline = TemplateDiscipline::create([
                    'set_id' => $set->id,
                    'code' => $disciplineData['code'],
                    'name' => $disciplineData['name'],
                    'color_hex' => $disciplineData['color'],
                    'order_index' => $disciplineData['order'],
                ]);
                $disciplineMap[$disciplineData['code']] = $discipline->id;
            }

            // Create tasks
            $tasks = [
                // Concept Phase
                [
                    'code' => 'ARC-C01',
                    'name' => 'Master Layout',
                    'phase' => 'CONCEPT',
                    'discipline' => 'ARC',
                    'description' => 'Zoning, flow, program analysis',
                    'est_duration_days' => 3,
                    'role_key' => 'lead_architect',
                    'deliverable_type' => 'layout_dwg',
                    'order' => 1,
                    'depends_on' => [],
                ],
                [
                    'code' => 'ARC-C02',
                    'name' => 'Concept Sketches',
                    'phase' => 'CONCEPT',
                    'discipline' => 'ARC',
                    'description' => 'Initial design sketches and concepts',
                    'est_duration_days' => 5,
                    'role_key' => 'architect',
                    'deliverable_type' => 'sketch',
                    'order' => 2,
                    'depends_on' => ['ARC-C01'],
                ],
                [
                    'code' => 'MEP-C01',
                    'name' => 'MEP Concept',
                    'phase' => 'CONCEPT',
                    'discipline' => 'MEP',
                    'description' => 'Initial MEP system concepts',
                    'est_duration_days' => 2,
                    'role_key' => 'mep_engineer',
                    'deliverable_type' => 'concept_doc',
                    'order' => 1,
                    'depends_on' => ['ARC-C01'],
                ],
                [
                    'code' => 'STR-C01',
                    'name' => 'Structural Concept',
                    'phase' => 'CONCEPT',
                    'discipline' => 'STR',
                    'description' => 'Initial structural system concepts',
                    'est_duration_days' => 2,
                    'role_key' => 'structural_engineer',
                    'deliverable_type' => 'concept_doc',
                    'order' => 1,
                    'depends_on' => ['ARC-C01'],
                ],
                // Design Phase
                [
                    'code' => 'ARC-D01',
                    'name' => 'Design Development',
                    'phase' => 'DESIGN',
                    'discipline' => 'ARC',
                    'description' => 'Detailed design development',
                    'est_duration_days' => 10,
                    'role_key' => 'lead_architect',
                    'deliverable_type' => 'design_dwg',
                    'order' => 1,
                    'depends_on' => ['ARC-C02'],
                ],
                [
                    'code' => 'LND-PANO',
                    'name' => 'Landscape Panorama',
                    'phase' => 'DESIGN',
                    'discipline' => 'LND',
                    'description' => 'Landscape panorama design',
                    'est_duration_days' => 3,
                    'role_key' => 'landscape_architect',
                    'deliverable_type' => 'design_dwg',
                    'order' => 1,
                    'is_optional' => true,
                    'depends_on' => ['ARC-D01'],
                ],
            ];

            $taskMap = [];
            foreach ($tasks as $taskData) {
                $task = TemplateTask::create([
                    'set_id' => $set->id,
                    'phase_id' => $phaseMap[$taskData['phase']],
                    'discipline_id' => $disciplineMap[$taskData['discipline']],
                    'code' => $taskData['code'],
                    'name' => $taskData['name'],
                    'description' => $taskData['description'],
                    'est_duration_days' => $taskData['est_duration_days'],
                    'role_key' => $taskData['role_key'],
                    'deliverable_type' => $taskData['deliverable_type'],
                    'order_index' => $taskData['order'],
                    'is_optional' => $taskData['is_optional'] ?? false,
                ]);
                $taskMap[$taskData['code']] = $task->id;
            }

            // Create dependencies
            foreach ($tasks as $taskData) {
                if (!empty($taskData['depends_on'])) {
                    $taskId = $taskMap[$taskData['code']];
                    foreach ($taskData['depends_on'] as $dependsOnCode) {
                        $dependsOnTaskId = $taskMap[$dependsOnCode] ?? null;
                        if ($dependsOnTaskId) {
                            TemplateTaskDependency::create([
                                'set_id' => $set->id,
                                'task_id' => $taskId,
                                'depends_on_task_id' => $dependsOnTaskId,
                            ]);
                        }
                    }
                }
            }

            // Create presets
            $presets = [
                [
                    'code' => 'HOUSE',
                    'name' => 'Townhouse',
                    'description' => 'Template preset for townhouse projects',
                    'filters' => [
                        'disciplines' => ['ARC', 'MEP'],
                        'exclude' => ['LND-PANO'],
                    ],
                ],
                [
                    'code' => 'HIGH_RISE',
                    'name' => 'High-rise Building',
                    'description' => 'Template preset for high-rise building projects',
                    'filters' => [
                        'phases' => ['CONCEPT', 'DESIGN'],
                        'disciplines' => ['ARC', 'MEP', 'STR'],
                    ],
                ],
                [
                    'code' => 'COMMERCIAL',
                    'name' => 'Commercial',
                    'description' => 'Template preset for commercial projects',
                    'filters' => [
                        'phases' => ['CONCEPT', 'DESIGN', 'CONSTRUCTION'],
                        'disciplines' => ['ARC', 'MEP', 'STR', 'LND'],
                    ],
                ],
            ];

            foreach ($presets as $presetData) {
                TemplatePreset::create([
                    'set_id' => $set->id,
                    'code' => $presetData['code'],
                    'name' => $presetData['name'],
                    'description' => $presetData['description'],
                    'filters' => $presetData['filters'],
                ]);
            }

            $this->command->info("Created template set: {$set->name} (v{$set->version})");
            $this->command->info("  - Phases: " . count($phases));
            $this->command->info("  - Disciplines: " . count($disciplines));
            $this->command->info("  - Tasks: " . count($tasks));
            $this->command->info("  - Presets: " . count($presets));
        });
    }
}


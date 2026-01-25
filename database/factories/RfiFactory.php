<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

class RfiFactory extends Factory
{
    protected $model = Rfi::class;

    public function definition(): array
    {
        $table = (new Rfi())->getTable();
        $hasTable = Schema::hasTable($table);

        $data = [];

        // Minimal, schema-safe fields (only set if the column exists)
        if ($hasTable && Schema::hasColumn($table, 'project_id')) {
            $data['project_id'] = Project::factory();
        }

        if ($hasTable && Schema::hasColumn($table, 'rfi_number')) {
            $data['rfi_number'] = 'RFI-' . strtoupper($this->faker->unique()->regexify('[A-Z0-9]{8}'));
        }

        // Common text columns (set only if exists)
        $textCandidates = [
            'title'       => $this->faker->sentence(6),
            'subject'     => $this->faker->sentence(6),
            'name'        => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'question'    => $this->faker->sentence(12),
            'content'     => $this->faker->paragraph(),
            'note'        => $this->faker->sentence(10),
        ];
        foreach ($textCandidates as $col => $val) {
            if ($hasTable && Schema::hasColumn($table, $col)) {
                $data[$col] = $val;
            }
        }

        // Common status columns
        $statusCandidates = [
            'status' => 'pending',
            'state'  => 'open',
        ];
        foreach ($statusCandidates as $col => $val) {
            if ($hasTable && Schema::hasColumn($table, $col)) {
                $data[$col] = $val;
            }
        }

        // Common user linkage columns
        $userCandidates = [
            'user_id',
            'created_by',
            'created_by_id',
            'requester_id',
            'author_id',
        ];
        foreach ($userCandidates as $col) {
            if ($hasTable && Schema::hasColumn($table, $col)) {
                $data[$col] = User::factory();
                break; // pick one
            }
        }

        if ($hasTable && Schema::hasColumn($table, 'asked_by')) {
            $data['asked_by'] = User::factory();
        }

        return $data;
    }

    public function configure()
    {
        return $this->afterMaking(function (Rfi $rfi) {
            $this->alignTenantFromProject($rfi);
        })->afterCreating(function (Rfi $rfi) {
            $this->alignTenantFromProject($rfi);
            if ($rfi->isDirty()) {
                $rfi->save();
            }
        });
    }

    private function alignTenantFromProject(Rfi $rfi): void
    {
        $table = $rfi->getTable();
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        if (!empty($rfi->tenant_id)) {
            return;
        }

        // If project is loaded/available, inherit tenant_id
        if (!empty($rfi->project_id) && method_exists($rfi, 'project')) {
            $project = $rfi->project;
            if ($project && !empty($project->tenant_id)) {
                $rfi->tenant_id = $project->tenant_id;
            }
        }
    }
}

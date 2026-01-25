<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

class SubmittalFactory extends Factory
{
    protected $model = Submittal::class;

    public function definition(): array
    {
        $table = (new Submittal())->getTable();
        $hasTable = Schema::hasTable($table);

        $data = [];

        if ($hasTable && Schema::hasColumn($table, 'project_id')) {
            $data['project_id'] = Project::factory();
        }

        $textCandidates = [
            'title'       => $this->faker->sentence(6),
            'subject'     => $this->faker->sentence(6),
            'name'        => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'content'     => $this->faker->paragraph(),
            'note'        => $this->faker->sentence(10),
        ];
        foreach ($textCandidates as $col => $val) {
            if ($hasTable && Schema::hasColumn($table, $col)) {
                $data[$col] = $val;
            }
        }

        $statusCandidates = [
            'status' => 'draft',
            'state'  => 'draft',
        ];
        foreach ($statusCandidates as $col => $val) {
            if ($hasTable && Schema::hasColumn($table, $col)) {
                $data[$col] = $val;
            }
        }

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
                break;
            }
        }

        return $data;
    }

    public function configure()
    {
        return $this->afterMaking(function (Submittal $submittal) {
            $this->alignTenantFromProject($submittal);
        })->afterCreating(function (Submittal $submittal) {
            $this->alignTenantFromProject($submittal);
            if ($submittal->isDirty()) {
                $submittal->save();
            }
        });
    }

    private function alignTenantFromProject(Submittal $submittal): void
    {
        $table = $submittal->getTable();
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'tenant_id')) {
            return;
        }

        if (!is_null($submittal->tenant_id)) {
            return;
        }

        if (!empty($submittal->project_id) && method_exists($submittal, 'project')) {
            $project = $submittal->project;
            if ($project && !empty($project->tenant_id)) {
                $submittal->tenant_id = $project->tenant_id;
            }
        }
    }
}

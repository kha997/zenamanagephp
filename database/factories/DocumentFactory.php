<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $project = Project::factory()->create();
        $this->ensureLegacyProjectExists($project);

        return [
            'id' => \Illuminate\Support\Str::ulid(),
            'project_id' => $project->id,
            'tenant_id' => $project->tenant_id,
            'uploaded_by' => User::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->words(3, true),
            'original_name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'mime_type' => 'application/pdf',
            'file_type' => $this->faker->randomElement(['drawing', 'spec', 'report']),
            'file_hash' => $this->faker->md5,
            'version' => '1.0',
            'status' => $this->faker->randomElement(['draft', 'review', 'approved', 'published']),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Document $document) {
            $this->ensureLegacyProjectExistsById($document->project_id);
        })->afterCreating(function (Document $document) {
            $this->ensureLegacyProjectExistsById($document->project_id);
        });
    }

    private function ensureLegacyProjectExists(Project $project): void
    {
        if (DB::table('zena_projects')->where('id', $project->id)->exists()) {
            return;
        }

        DB::table('zena_projects')->insert([
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'description' => $project->description,
            'client_id' => null,
            'status' => $this->mapLegacyProjectStatus($project->status),
            'start_date' => $project->start_date,
            'end_date' => $project->end_date,
            'budget' => $project->budget_total,
            'settings' => json_encode($project->settings ?? []),
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ]);
    }

    private function ensureLegacyProjectExistsById(?string $projectId): void
    {
        if (! $projectId) {
            return;
        }

        $project = Project::find($projectId);

        if (! $project) {
            return;
        }

        $this->ensureLegacyProjectExists($project);
    }

    private function mapLegacyProjectStatus(string $status): string
    {
        return match ($status) {
            'in_progress' => 'active',
            default => $status,
        };
    }
}

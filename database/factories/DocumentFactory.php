<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

    private const ZENA_PROJECT_STATUSES = [
        'planning',
        'active',
        'on_hold',
        'completed',
        'cancelled',
    ];

    private const ZENA_PROJECT_STATUS_MAP = [
        'in_progress' => 'active',
        'pending' => 'planning',
        'draft' => 'planning',
        'paused' => 'on_hold',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $tenant = Tenant::factory();
        $uploader = User::factory()->for($tenant, 'tenant');

        return [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => $tenant,
            'uploaded_by' => $uploader,
            'name' => $this->faker->words(3, true),
            'original_name' => $this->faker->word() . '.pdf',
            'description' => $this->faker->sentence(),
            'file_path' => '/documents/' . $this->faker->uuid() . '.pdf',
            'file_type' => 'pdf',
            'file_size' => $this->faker->numberBetween(1000, 10000000),
            'file_hash' => md5($this->faker->uuid()),
            'mime_type' => 'application/pdf',
            'version' => '1.0',
            'status' => $this->faker->randomElement(['draft', 'review', 'approved', 'published']),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function configure(): self
    {
        return $this->afterMaking(function (Document $document) {
            $this->ensureZenaProjectEntry($document);
        })->afterCreating(function (Document $document) {
            if ($document->created_by !== null && $document->updated_by !== null) {
                return;
            }

            $creatorId = $document->created_by ?? $document->uploaded_by;

            if ($creatorId === null) {
                $creatorId = User::factory()->create([
                    'tenant_id' => $document->tenant_id,
                ])->id;
            }

            $document->updateQuietly([
                'created_by' => $document->created_by ?? $creatorId,
                'updated_by' => $document->updated_by ?? $creatorId,
            ]);
        });
    }

    private function ensureZenaProjectEntry(Document $document): void
    {
        if (!$document->project_id || !Schema::hasTable('zena_projects')) {
            return;
        }

        if (DB::table('zena_projects')->where('id', $document->project_id)->exists()) {
            return;
        }

        $project = Project::find($document->project_id);

        DB::table('zena_projects')->insert([
            'id' => $document->project_id,
            'code' => $project?->code ?? 'PRJ-' . strtoupper(Str::random(8)),
            'name' => $project?->name ?? ('Project ' . strtoupper(Str::random(4))),
            'description' => $project?->description,
            'client_id' => $project?->client_id,
            'status' => $this->normalizeZenaProjectStatus($project?->status),
            'start_date' => $project?->start_date,
            'end_date' => $project?->end_date,
            'budget' => $project?->budget_total ?? null,
            'settings' => $project?->settings ? json_encode($project->settings) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeZenaProjectStatus(?string $status): string
    {
        if ($status && isset(self::ZENA_PROJECT_STATUS_MAP[$status])) {
            return self::ZENA_PROJECT_STATUS_MAP[$status];
        }

        if ($status && in_array($status, self::ZENA_PROJECT_STATUSES, true)) {
            return $status;
        }

        return 'planning';
    }
}

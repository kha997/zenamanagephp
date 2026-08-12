<?php declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentApproverAssignmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_table_has_nullable_approver_id_column(): void
    {
        self::assertTrue(Schema::hasColumn('documents', 'approver_id'));

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        self::assertNull($document->fresh()->approver_id);
    }

    public function test_document_approver_assignments_table_exists_with_expected_columns(): void
    {
        self::assertTrue(Schema::hasTable('document_approver_assignments'));
        self::assertTrue(Schema::hasColumns('document_approver_assignments', [
            'id', 'tenant_id', 'document_id', 'actor_id',
            'previous_approver_id', 'new_approver_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_document_approver_assignments_row_can_be_inserted_with_null_previous_and_new(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $id = \Illuminate\Support\Str::ulid()->toBase32();
        \Illuminate\Support\Facades\DB::table('document_approver_assignments')->insert([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertDatabaseHas('document_approver_assignments', ['id' => $id]);
    }
}

<?php declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmittalRevisionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_submittal_revisions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('submittal_revisions'));
        $this->assertTrue(Schema::hasColumns('submittal_revisions', [
            'id', 'tenant_id', 'submittal_id', 'revision_no', 'revision_summary',
            'title', 'description', 'file_url', 'attachment_manifest',
            'submitted_by', 'submitted_at', 'decision', 'decided_by',
            'decided_at', 'decision_comments', 'created_at',
        ]));
    }

    public function test_submittals_table_has_current_revision_no(): void
    {
        $this->assertTrue(Schema::hasColumn('submittals', 'current_revision_no'));
    }
}

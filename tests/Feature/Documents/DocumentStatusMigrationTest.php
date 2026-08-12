<?php declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\DocumentApprovalEvent;
use App\Models\DocumentVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class DocumentStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    private static bool $mysqlDriverReported = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('GAP032_REQUIRE_MYSQL') === '1') {
            $driver = DB::connection()->getDriverName();
            self::assertSame('mysql', $driver);

            if (! self::$mysqlDriverReported) {
                fwrite(STDOUT, "GAP-032 migration driver: {$driver}\n");
                self::$mysqlDriverReported = true;
            }
        }
    }

    public function test_migration_adds_nullable_lifecycle_and_approval_columns_without_rewriting_legacy_row(): void
    {
        $migration = $this->canonicalStatusMigration();
        $migration->down();
        $document = $this->createLegacyDocument('review', '{"status":"review","label":"Original"}');
        $before = DB::table('documents')->where('id', $document->id)->first(['status', 'metadata']);

        $migration->up();

        $after = DB::table('documents')->where('id', $document->id)->first([
            'status',
            'metadata',
            'lifecycle_status',
            'approval_status',
        ]);
        self::assertTrue(Schema::hasColumns('documents', ['lifecycle_status', 'approval_status']));
        self::assertNull($after->lifecycle_status);
        self::assertNull($after->approval_status);
        self::assertSame($before->status, $after->status);
        self::assertSame($before->metadata, $after->metadata);
    }

    /** @group slow */
    public function test_migration_round_trip_preserves_existing_status_and_metadata_on_sqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            self::markTestSkipped('dependency: the explicit down/up round-trip assertion is SQLite-specific.');
        }

        $document = $this->createLegacyDocument('review', '{"status":"review","bytes":"unchanged"}');
        $before = DB::table('documents')->where('id', $document->id)->first(['status', 'metadata']);
        $migration = $this->canonicalStatusMigration();

        $migration->down();
        self::assertFalse(Schema::hasColumn('documents', 'lifecycle_status'));
        self::assertFalse(Schema::hasColumn('documents', 'approval_status'));
        $afterDown = DB::table('documents')->where('id', $document->id)->first(['status', 'metadata']);
        self::assertSame($before->status, $afterDown->status);
        self::assertSame($before->metadata, $afterDown->metadata);

        $migration->up();
        $afterUp = DB::table('documents')->where('id', $document->id)->first([
            'status',
            'metadata',
            'lifecycle_status',
            'approval_status',
        ]);
        self::assertNull($afterUp->lifecycle_status);
        self::assertNull($afterUp->approval_status);
        self::assertSame($before->status, $afterUp->status);
        self::assertSame($before->metadata, $afterUp->metadata);
    }

    public function test_document_model_exposes_resolved_canonical_values_for_null_legacy_columns(): void
    {
        $cases = [
            'review' => ['in-review', 'not-submitted'],
            'submitted' => [null, 'awaiting-approval'],
            'approved' => [null, 'approved'],
            'rejected' => [null, 'rejected'],
        ];

        foreach ($cases as $legacy => [$expectedLifecycle, $expectedApproval]) {
            $document = $this->createLegacyDocument($legacy, json_encode(['status' => $legacy], JSON_THROW_ON_ERROR));
            $serialized = $document->fresh()->toArray();

            self::assertSame($expectedLifecycle, $serialized['lifecycle_status']);
            self::assertSame($expectedApproval, $serialized['approval_status']);
            self::assertSame($legacy, $serialized['status']);
            self::assertSame($legacy, $serialized['metadata']['status']);
            self::assertNull($document->fresh()->getRawOriginal('lifecycle_status'));
            self::assertNull($document->fresh()->getRawOriginal('approval_status'));
        }
    }

    public function test_unknown_legacy_status_serializes_null_canonical_dimensions_and_original_legacy_projection(): void
    {
        $document = $this->createLegacyDocument(
            'legacy-CUSTOM_state',
            '{"status":"legacy-CUSTOM_state","preserve":"yes"}'
        );
        $before = DB::table('documents')->where('id', $document->id)->first([
            'status',
            'metadata',
            'lifecycle_status',
            'approval_status',
        ]);

        $serialized = $document->fresh()->toArray();

        self::assertNull($serialized['lifecycle_status']);
        self::assertNull($serialized['approval_status']);
        self::assertSame('legacy-CUSTOM_state', $serialized['status']);
        self::assertSame('legacy-CUSTOM_state', $serialized['metadata']['status']);
        self::assertSame('yes', $serialized['metadata']['preserve']);
        $after = DB::table('documents')->where('id', $document->id)->first([
            'status',
            'metadata',
            'lifecycle_status',
            'approval_status',
        ]);
        self::assertEquals($before, $after);
    }

    public function test_unknown_legacy_status_preserves_differing_metadata_status_during_serialization(): void
    {
        $document = $this->createLegacyDocument(
            'legacy-document-state',
            '{"status":"metadata-only-state","preserve":"yes"}'
        );
        $before = DB::table('documents')->where('id', $document->id)->value('metadata');

        $serialized = $document->fresh()->toArray();

        self::assertNull($serialized['lifecycle_status']);
        self::assertNull($serialized['approval_status']);
        self::assertSame('legacy-document-state', $serialized['status']);
        self::assertSame('metadata-only-state', $serialized['metadata']['status']);
        self::assertSame($before, DB::table('documents')->where('id', $document->id)->value('metadata'));
    }

    public function test_unknown_legacy_status_keeps_absent_metadata_status_absent_during_serialization(): void
    {
        $document = $this->createLegacyDocument('legacy-document-state', '{"preserve":"yes"}');
        $before = DB::table('documents')->where('id', $document->id)->value('metadata');

        $serialized = $document->fresh()->toArray();

        self::assertNull($serialized['lifecycle_status']);
        self::assertNull($serialized['approval_status']);
        self::assertSame('legacy-document-state', $serialized['status']);
        self::assertArrayNotHasKey('status', $serialized['metadata']);
        self::assertSame('yes', $serialized['metadata']['preserve']);
        self::assertSame($before, DB::table('documents')->where('id', $document->id)->value('metadata'));
    }

    public function test_approval_events_schema_is_tenant_scoped_append_only_and_version_bound(): void
    {
        $columns = Schema::getColumnListing('document_approval_events');
        foreach ([
            'id',
            'tenant_id',
            'document_id',
            'document_version_id',
            'event',
            'from_approval_status',
            'to_approval_status',
            'actor_id',
            'note',
            'context',
            'created_at',
            'updated_at',
        ] as $column) {
            self::assertContains($column, $columns);
        }

        $indexes = collect(Schema::getIndexes('document_approval_events'))->keyBy('name');
        self::assertSame(['tenant_id'], $indexes->get('document_approval_events_tenant_id_index')['columns'] ?? null);

        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'event' => 'submitted',
            'from_approval_status' => 'not-submitted',
            'to_approval_status' => 'awaiting-approval',
            'actor_id' => $actor->id,
            'note' => 'Ready for decision',
            'context' => ['source' => 'workflow'],
        ]);
        self::assertSame($version->id, $event->document_version_id);
        self::assertSame($document->tenant_id, $event->tenant_id);

        $event->note = 'Rewritten history';
        $this->expectException(LogicException::class);
        $event->save();
    }

    public function test_version_bound_approval_event_creation_rejects_missing_or_foreign_version(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        [$otherDocument, $otherVersion] = $this->createVersionedDocument();

        try {
            DocumentApprovalEvent::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'document_version_id' => null,
                'event' => 'approved',
                'from_approval_status' => 'awaiting-approval',
                'to_approval_status' => 'approved',
                'actor_id' => $actor->id,
                'context' => [],
            ]);
            self::fail('A decision without a version reference was accepted.');
        } catch (LogicException) {
            self::assertDatabaseMissing('document_approval_events', [
                'document_id' => $document->id,
                'event' => 'approved',
            ]);
        }

        try {
            DocumentApprovalEvent::create([
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'document_version_id' => $otherVersion->id,
                'event' => 'submitted',
                'from_approval_status' => 'not-submitted',
                'to_approval_status' => 'awaiting-approval',
                'actor_id' => $actor->id,
                'context' => [],
            ]);
            self::fail('A version owned by another document was accepted.');
        } catch (LogicException) {
            self::assertSame($otherDocument->id, $otherVersion->document_id);
            self::assertNotSame($version->id, $otherVersion->id);
            self::assertDatabaseMissing('document_approval_events', [
                'document_id' => $document->id,
                'document_version_id' => $otherVersion->id,
            ]);
        }
    }

    public function test_legacy_reactivation_is_only_event_that_allows_explicit_null_version(): void
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'current_version_id' => null,
            'status' => 'archived',
            'metadata' => ['status' => 'archived'],
        ]);

        $event = DocumentApprovalEvent::create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'document_version_id' => null,
            'event' => 'reactivated',
            'from_approval_status' => 'not-submitted',
            'to_approval_status' => 'not-submitted',
            'actor_id' => $actor->id,
            'context' => ['legacy_without_current_version' => true],
        ]);
        self::assertNull($event->document_version_id);

        $this->expectException(LogicException::class);
        DocumentApprovalEvent::create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'document_version_id' => null,
            'event' => 'reactivated',
            'from_approval_status' => 'not-submitted',
            'to_approval_status' => 'not-submitted',
            'actor_id' => $actor->id,
            'context' => [],
        ]);
    }

    public function test_approval_events_cannot_be_deleted(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'event' => 'submitted',
            'from_approval_status' => 'not-submitted',
            'to_approval_status' => 'awaiting-approval',
            'actor_id' => $actor->id,
            'context' => [],
        ]);

        $this->expectException(LogicException::class);
        $event->delete();
    }

    public function test_approval_events_cannot_be_updated_quietly(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create($this->validEventAttributes($document, $version, $actor));

        try {
            $event->updateQuietly(['note' => 'Rewritten quietly']);
            self::fail('Quiet update rewrote append-only approval history.');
        } catch (LogicException) {
            self::assertDatabaseHas('document_approval_events', [
                'id' => $event->id,
                'note' => 'Ready for decision',
            ]);
        }
    }

    public function test_approval_events_cannot_be_deleted_quietly(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create($this->validEventAttributes($document, $version, $actor));

        try {
            $event->deleteQuietly();
            self::fail('Quiet delete removed append-only approval history.');
        } catch (LogicException) {
            self::assertDatabaseHas('document_approval_events', ['id' => $event->id]);
        }
    }

    public function test_approval_events_cannot_be_mass_updated(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create($this->validEventAttributes($document, $version, $actor));

        try {
            DocumentApprovalEvent::query()->whereKey($event->id)->update(['note' => 'Mass rewrite']);
            self::fail('Mass update rewrote append-only approval history.');
        } catch (LogicException) {
            self::assertDatabaseHas('document_approval_events', [
                'id' => $event->id,
                'note' => 'Ready for decision',
            ]);
        }
    }

    public function test_approval_events_cannot_be_mass_deleted(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $event = DocumentApprovalEvent::create($this->validEventAttributes($document, $version, $actor));

        try {
            DocumentApprovalEvent::query()->whereKey($event->id)->delete();
            self::fail('Mass delete removed append-only approval history.');
        } catch (LogicException) {
            self::assertDatabaseHas('document_approval_events', ['id' => $event->id]);
        }
    }

    public function test_invalid_approval_event_cannot_bypass_validation_with_create_quietly(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $attributes = $this->validEventAttributes($document, $version, $actor);
        $attributes['event'] = 'approved';
        $attributes['to_approval_status'] = 'approved';
        $attributes['document_version_id'] = null;

        try {
            DocumentApprovalEvent::query()->createQuietly($attributes);
            self::fail('Quiet creation bypassed required version validation.');
        } catch (LogicException) {
            self::assertDatabaseMissing('document_approval_events', [
                'document_id' => $document->id,
                'event' => 'approved',
            ]);
        }
    }

    public function test_invalid_approval_event_cannot_bypass_validation_with_mass_insert(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $attributes = $this->validEventAttributes($document, $version, $actor);
        $attributes['id'] = (string) Str::ulid();
        $attributes['event'] = 'invalid-event';
        $attributes['context'] = json_encode([], JSON_THROW_ON_ERROR);
        $attributes['created_at'] = now();
        $attributes['updated_at'] = now();

        try {
            DocumentApprovalEvent::query()->insert($attributes);
            self::fail('Mass insert bypassed approval-event validation.');
        } catch (LogicException) {
            self::assertDatabaseMissing('document_approval_events', ['id' => $attributes['id']]);
        }
    }

    public function test_invalid_approval_event_cannot_bypass_validation_with_mass_upsert(): void
    {
        [$document, $version, $actor] = $this->createVersionedDocument();
        $attributes = $this->validEventAttributes($document, $version, $actor);
        $attributes['id'] = (string) Str::ulid();
        $attributes['event'] = 'invalid-event';
        $attributes['context'] = json_encode([], JSON_THROW_ON_ERROR);

        try {
            DocumentApprovalEvent::query()->upsert([$attributes], ['id'], ['note']);
            self::fail('Mass upsert bypassed approval-event validation.');
        } catch (LogicException) {
            self::assertDatabaseMissing('document_approval_events', ['id' => $attributes['id']]);
        }
    }

    private function createLegacyDocument(string $status, string $metadata): Document
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => $status,
        ]);
        DB::table('documents')->where('id', $document->id)->update([
            'status' => $status,
            'metadata' => $metadata,
        ]);

        return $document->fresh();
    }

    /** @return array{Document, DocumentVersion, User} */
    private function createVersionedDocument(): array
    {
        $tenant = Tenant::factory()->create();
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $actor->id,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
            'status' => 'draft',
            'metadata' => ['status' => 'draft'],
        ]);
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => '/documents/version-1.pdf',
            'storage_driver' => 'local',
            'created_by' => $actor->id,
        ]);

        return [$document, $version, $actor];
    }

    /** @return array<string, mixed> */
    private function validEventAttributes(Document $document, DocumentVersion $version, User $actor): array
    {
        return [
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'event' => 'submitted',
            'from_approval_status' => 'not-submitted',
            'to_approval_status' => 'awaiting-approval',
            'actor_id' => $actor->id,
            'note' => 'Ready for decision',
            'context' => [],
        ];
    }

    private function canonicalStatusMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_08_10_230000_add_canonical_status_dimensions_to_documents.php');

        return $migration;
    }
}

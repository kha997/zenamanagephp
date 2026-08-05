<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Document;
use PHPUnit\Framework\TestCase;

class DocumentAccessorTest extends TestCase
{
    public function test_decision_accessors_return_null_for_null_metadata(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => null]);

        $this->assertNull($document->decision_by_id);
        $this->assertNull($document->decision_at);
        $this->assertNull($document->decision_note);
    }

    public function test_decision_accessors_return_null_when_metadata_missing_keys(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode(['status' => 'draft'])]);

        $this->assertNull($document->decision_by_id);
        $this->assertNull($document->decision_at);
        $this->assertNull($document->decision_note);
    }

    public function test_decision_accessors_read_present_values(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode([
            'decision_by' => 'user-123',
            'decision_at' => '2026-08-04T10:00:00+00:00',
            'decision_note' => 'ok',
        ])]);

        $this->assertSame('user-123', $document->decision_by_id);
        $this->assertSame('ok', $document->decision_note);
        $this->assertNotNull($document->decision_at);
        $this->assertSame('2026-08-04', $document->decision_at->format('Y-m-d'));
    }

    public function test_decision_accessors_are_not_appended_to_array_serialization(): void
    {
        $document = new Document();
        $document->setRawAttributes(['metadata' => json_encode(['decision_by' => 'user-123'])]);

        $array = $document->toArray();

        $this->assertArrayNotHasKey('decision_by_id', $array);
        $this->assertArrayNotHasKey('decision_at', $array);
        $this->assertArrayNotHasKey('decision_note', $array);
    }
}

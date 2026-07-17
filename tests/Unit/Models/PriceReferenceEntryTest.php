<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\PriceReferenceEntry;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceReferenceEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_latest_for_returns_the_newest_entry_by_evidenced_at(): void
    {
        $tenant = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1500000,
            'evidenced_at' => '2026-01-01',
        ]);

        $newest = PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
            'unit_price' => 1800000,
            'evidenced_at' => '2026-07-01',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'BT-MONG', 'm3');

        $this->assertNotNull($result);
        $this->assertSame($newest->id, $result->id);
        $this->assertEqualsWithDelta(1800000, $result->unit_price, 0.01);
    }

    public function test_latest_for_returns_null_when_no_match(): void
    {
        $tenant = Tenant::factory()->create();

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'NO-SUCH-CODE', 'm3');

        $this->assertNull($result);
    }

    public function test_latest_for_is_tenant_isolated(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenantB->id, 'BT-MONG', 'm3');

        $this->assertNull($result);
    }

    public function test_unit_mismatch_does_not_match(): void
    {
        $tenant = Tenant::factory()->create();

        PriceReferenceEntry::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'work_item_code' => 'BT-MONG',
            'unit' => 'm3',
        ]);

        $result = PriceReferenceEntry::latestFor((string) $tenant->id, 'BT-MONG', 'm2');

        $this->assertNull($result);
    }
}

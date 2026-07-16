<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSourceFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_can_store_source_opportunity_and_quote_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an test',
            'code' => 'PRJ-TESTSRC1',
            'status' => 'planning',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-TESTSRC1',
            'title' => 'Hop dong test',
            'source_opportunity_id' => 'opp_123',
            'source_quote_id' => 'quote_123',
            'source_quote_revision' => 3,
        ]);

        $contract->refresh();

        $this->assertSame('opp_123', $contract->source_opportunity_id);
        $this->assertSame('quote_123', $contract->source_quote_id);
        $this->assertSame(3, $contract->source_quote_revision);
    }
}

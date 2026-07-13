<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractBoqTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], []);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-BOQ-01',
            'title' => 'HĐ thử BOQ',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $this->user->id,
        ]);
    }

    public function test_contract_has_one_boq_with_contract_id(): void
    {
        $boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-BOQ-01',
            'name' => 'Bảng khối lượng HĐ CTR-BOQ-01',
        ]);

        $line = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc',
            'quantity' => 100,
            'unit' => 'm',
            'unit_price' => 1500000,
        ]);

        $this->refresh();

        $this->assertNotNull($this->contract->boq);
        $this->assertSame((string) $boq->id, (string) $this->contract->boq->id);
        $this->assertSame(1500000.0, $this->contract->boq->lineItems->first()->unit_price);
    }

    public function test_project_scoped_boq_without_contract_id_still_works(): void
    {
        $boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'BOQ-PRJ-01',
            'name' => 'BOQ dự án',
        ]);

        $this->assertNull($boq->contract_id);
        $this->assertSame((string) $boq->id, (string) Boq::query()->find($boq->id)->id);
    }

    public function test_boq_line_item_unit_price_nullable(): void
    {
        $boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-BOQ-02',
            'name' => 'BOQ không giá',
        ]);

        $line = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $boq->id,
            'name' => 'Hạng mục chưa có giá',
            'quantity' => 50,
            'unit' => 'kg',
        ]);

        $this->assertNull($line->unit_price);
    }

    private function refresh(): void
    {
        $this->contract->refresh();
        $this->contract->load('boq.lineItems');
    }
}

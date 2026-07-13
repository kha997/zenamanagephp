<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class PaymentCertificateTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;
    private Boq $boq;
    private BoqLineItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], []);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-CERT-01',
            'title' => 'HĐ thử chứng chỉ',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $this->user->id,
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-CERT-01',
            'name' => 'Bảng khối lượng HĐ CTR-CERT-01',
        ]);

        $this->line = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc',
            'quantity' => 100,
            'unit' => 'm',
            'unit_price' => 200000,
        ]);
    }

    public function test_payment_certificate_uses_tenant_scope(): void
    {
        foreach ([PaymentCertificate::class, PaymentCertificateLine::class] as $model) {
            $this->assertContains(
                TenantScope::class,
                class_uses_recursive($model),
                "{$model} must use App\\Traits\\TenantScope"
            );
        }
    }

    public function test_create_certificate_with_one_line(): void
    {
        $cert = PaymentCertificate::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'period_no' => 1,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => PaymentCertificate::STATUS_DRAFT,
        ]);

        $certLine = PaymentCertificateLine::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'payment_certificate_id' => (string) $cert->id,
            'boq_line_item_id' => (string) $this->line->id,
            'qty_this_period' => 30,
            'unit_price_snapshot' => 200000,
            'amount_this_period' => 6000000,
        ]);

        $this->assertSame(1, PaymentCertificate::query()->count());
        $this->assertSame(1, $cert->lines()->count());
        $this->assertSame(6000000.0, $certLine->amount_this_period);
        // total_this_period is computed by controller on save lines / approve, defaults from DB
        $this->assertNotNull($cert);
    }

    public function test_can_transition_truth_table(): void
    {
        // draft → submitted: OK
        $this->assertTrue(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_DRAFT,
            PaymentCertificate::STATUS_SUBMITTED,
        ));

        // draft → approved: NOT allowed
        $this->assertFalse(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_DRAFT,
            PaymentCertificate::STATUS_APPROVED,
        ));

        // submitted → draft: OK (reject back)
        $this->assertTrue(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_SUBMITTED,
            PaymentCertificate::STATUS_DRAFT,
        ));

        // submitted → approved: OK
        $this->assertTrue(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_SUBMITTED,
            PaymentCertificate::STATUS_APPROVED,
        ));

        // approved → anything: NOT allowed
        $this->assertFalse(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_APPROVED,
            PaymentCertificate::STATUS_DRAFT,
        ));
        $this->assertFalse(PaymentCertificate::canTransition(
            PaymentCertificate::STATUS_APPROVED,
            PaymentCertificate::STATUS_SUBMITTED,
        ));
    }
}

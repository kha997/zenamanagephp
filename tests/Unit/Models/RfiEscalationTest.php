<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RfiEscalation;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_type_constants_have_expected_values(): void
    {
        $this->assertSame('manually_resolved', RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED);
        $this->assertSame('rfi_cancelled', RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED);
    }

    public function test_can_create_an_unresolved_escalation_with_immutable_origin_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $rfi = Rfi::factory()->create(['tenant_id' => $tenant->id]);

        $escalation = RfiEscalation::create([
            'rfi_id' => $rfi->id,
            'tenant_id' => $tenant->id,
            'escalated_to' => $user1->id,
            'escalated_by' => $user2->id,
            'escalated_at' => now(),
            'escalation_reason' => 'Client needs answer by tomorrow',
        ]);

        $this->assertNull($escalation->resolved_at);
        $this->assertNull($escalation->resolved_by);
        $this->assertNull($escalation->resolution);
        $this->assertNull($escalation->resolution_type);
        $this->assertSame('Client needs answer by tomorrow', $escalation->escalation_reason);
    }
}

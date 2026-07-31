<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityAppointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OpportunityAppointmentModelTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private function makeOpportunity(Tenant $tenant): array
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Opportunity for Appointment',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        return [$opportunity, $user];
    }

    #[DataProvider('appointmentTypeProvider')]
    public function test_appointment_can_be_created_for_each_valid_type(string $type): void
    {
        $tenant = Tenant::factory()->create();
        [$opportunity, $user] = $this->makeOpportunity($tenant);
        $assignee = $this->createTenantUser($tenant, [], ['admin'], []);

        $appointment = OpportunityAppointment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'type' => $type,
            'scheduled_at' => now()->addDay(),
            'location' => 'Customer site',
            'assigned_to' => (string) $assignee->id,
            'status' => OpportunityAppointment::STATUS_SCHEDULED,
            'outcome_notes' => null,
            'created_by' => (string) $user->id,
        ]);

        $this->assertNotEmpty($appointment->id);
        $this->assertSame((string) $tenant->id, (string) $appointment->tenant_id);
        $this->assertSame($type, $appointment->type);
        $this->assertSame(OpportunityAppointment::STATUS_SCHEDULED, $appointment->status);
        $this->assertSame((string) $opportunity->id, (string) $appointment->opportunity->id);
        $this->assertSame((string) $assignee->id, (string) $appointment->assignee->id);
    }

    public static function appointmentTypeProvider(): array
    {
        return [
            'consultation' => [OpportunityAppointment::TYPE_CONSULTATION],
            'survey' => [OpportunityAppointment::TYPE_SURVEY],
            'site_visit' => [OpportunityAppointment::TYPE_SITE_VISIT],
            'meeting' => [OpportunityAppointment::TYPE_MEETING],
        ];
    }

    public function test_can_transition_truth_table(): void
    {
        $this->assertTrue(
            OpportunityAppointment::canTransition(
                OpportunityAppointment::STATUS_SCHEDULED,
                OpportunityAppointment::STATUS_COMPLETED
            )
        );
        $this->assertTrue(
            OpportunityAppointment::canTransition(
                OpportunityAppointment::STATUS_SCHEDULED,
                OpportunityAppointment::STATUS_CANCELLED
            )
        );
        $this->assertTrue(
            OpportunityAppointment::canTransition(
                OpportunityAppointment::STATUS_SCHEDULED,
                OpportunityAppointment::STATUS_RESCHEDULED
            )
        );
        $this->assertFalse(
            OpportunityAppointment::canTransition(
                OpportunityAppointment::STATUS_SCHEDULED,
                OpportunityAppointment::STATUS_SCHEDULED
            )
        );

        foreach ([
            OpportunityAppointment::STATUS_COMPLETED,
            OpportunityAppointment::STATUS_CANCELLED,
            OpportunityAppointment::STATUS_RESCHEDULED,
        ] as $from) {
            foreach ([
                OpportunityAppointment::STATUS_SCHEDULED,
                OpportunityAppointment::STATUS_COMPLETED,
                OpportunityAppointment::STATUS_CANCELLED,
                OpportunityAppointment::STATUS_RESCHEDULED,
            ] as $to) {
                $this->assertFalse(OpportunityAppointment::canTransition($from, $to));
            }
        }
    }

    public function test_factory_defaults_created_by_within_the_same_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        [$opportunity] = $this->makeOpportunity($tenant);

        $appointment = OpportunityAppointment::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
        ]);

        $createdBy = User::query()->findOrFail($appointment->created_by);

        $this->assertSame((string) $tenant->id, (string) $appointment->tenant_id);
        $this->assertSame((string) $appointment->tenant_id, (string) $createdBy->tenant_id);
    }
}

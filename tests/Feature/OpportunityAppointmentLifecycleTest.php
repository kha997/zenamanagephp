<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\OpportunityAppointment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OpportunityAppointmentLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    private function makeOpportunity(Tenant $tenant, ?User $user = null): array
    {
        $user ??= $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Opportunity Appointment Test',
            'pipeline_stage' => Opportunity::STAGE_BRIEF_DISCOVERY,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        return ['user' => $user, 'opportunity' => $opportunity];
    }

    private function makeAppointment(
        Tenant $tenant,
        Opportunity $opportunity,
        User $user,
        string $status = OpportunityAppointment::STATUS_SCHEDULED,
        array $overrides = []
    ): OpportunityAppointment {
        return OpportunityAppointment::query()->create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'type' => OpportunityAppointment::TYPE_CONSULTATION,
            'scheduled_at' => Carbon::now()->addDay(),
            'location' => 'Showroom',
            'assigned_to' => (string) $user->id,
            'status' => $status,
            'outcome_notes' => null,
            'created_by' => (string) $user->id,
        ], $overrides));
    }

    public function test_store_creates_scheduled_appointments_for_both_types(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $assignee = $this->createTenantUser($tenant, ['name' => 'Consultant'], ['sales'], ['crm.view', 'crm.manage']);

        $this->actingAs($user);

        foreach ([OpportunityAppointment::TYPE_CONSULTATION, OpportunityAppointment::TYPE_SURVEY] as $index => $type) {
            $response = $this->post(route('operator.crm.opportunities.appointments.store', $opportunity->id), [
                'type' => $type,
                'scheduled_at' => Carbon::now()->addDays($index + 1)->format('Y-m-d H:i:s'),
                'location' => $type === OpportunityAppointment::TYPE_SURVEY ? 'Client site' : 'Office',
                'assigned_to' => (string) $assignee->id,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');
        }

        $this->assertSame(2, OpportunityAppointment::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('status', OpportunityAppointment::STATUS_SCHEDULED)
            ->count());
    }

    public function test_store_creates_scheduled_appointments_for_site_visit_and_meeting(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $assignee = $this->createTenantUser($tenant, ['name' => 'Host'], ['sales'], ['crm.view', 'crm.manage']);

        $this->actingAs($user);

        foreach ([OpportunityAppointment::TYPE_SITE_VISIT, OpportunityAppointment::TYPE_MEETING] as $index => $type) {
            $response = $this->post(route('operator.crm.opportunities.appointments.store', $opportunity->id), [
                'type' => $type,
                'scheduled_at' => Carbon::now()->addDays($index + 1)->format('Y-m-d H:i:s'),
                'location' => $type === OpportunityAppointment::TYPE_SITE_VISIT ? 'Công trình ABC' : 'Phòng họp A',
                'assigned_to' => (string) $assignee->id,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');
        }

        $this->assertSame(2, OpportunityAppointment::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('status', OpportunityAppointment::STATUS_SCHEDULED)
            ->count());
    }

    public function test_complete_requires_outcome_notes(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $appointment = $this->makeAppointment($tenant, $opportunity, $user);

        $response = $this->actingAs($user)
            ->post(route('operator.crm.appointments.complete', $appointment->id), []);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['outcome_notes']);

        $appointment->refresh();
        $this->assertSame(OpportunityAppointment::STATUS_SCHEDULED, $appointment->status);
    }

    public function test_complete_updates_status_and_writes_event_record(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $appointment = $this->makeAppointment($tenant, $opportunity, $user);

        $response = $this->actingAs($user)
            ->post(route('operator.crm.appointments.complete', $appointment->id), [
                'outcome_notes' => 'Client approved next survey step.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $appointment->refresh();
        $this->assertSame(OpportunityAppointment::STATUS_COMPLETED, $appointment->status);
        $this->assertSame('Client approved next survey step.', $appointment->outcome_notes);

        $this->assertSame(1, EventRecord::query()
            ->where('aggregate_type', 'opportunity_appointment')
            ->where('aggregate_id', (string) $appointment->id)
            ->where('event_key', 'opportunity_appointment.completed')
            ->count());
    }

    public function test_cancel_updates_status(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $appointment = $this->makeAppointment($tenant, $opportunity, $user);

        $response = $this->actingAs($user)
            ->post(route('operator.crm.appointments.cancel', $appointment->id), [
                'outcome_notes' => 'Client requested later follow-up.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $appointment->refresh();
        $this->assertSame(OpportunityAppointment::STATUS_CANCELLED, $appointment->status);
    }

    public function test_reschedule_marks_original_and_creates_new_appointment(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $appointment = $this->makeAppointment($tenant, $opportunity, $user, overrides: [
            'type' => OpportunityAppointment::TYPE_SURVEY,
            'location' => 'Original site',
        ]);
        $newSchedule = Carbon::now()->addDays(5)->startOfHour();

        $response = $this->actingAs($user)
            ->post(route('operator.crm.appointments.reschedule', $appointment->id), [
                'scheduled_at' => $newSchedule->format('Y-m-d H:i:s'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $appointment->refresh();
        $this->assertSame(OpportunityAppointment::STATUS_RESCHEDULED, $appointment->status);

        $replacement = OpportunityAppointment::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('status', OpportunityAppointment::STATUS_SCHEDULED)
            ->whereKeyNot((string) $appointment->id)
            ->first();

        $this->assertNotNull($replacement);
        $this->assertSame((string) $opportunity->id, (string) $replacement->opportunity_id);
        $this->assertSame(OpportunityAppointment::TYPE_SURVEY, $replacement->type);
        $this->assertSame('Original site', $replacement->location);
        $this->assertSame($newSchedule->toDateTimeString(), $replacement->scheduled_at->toDateTimeString());
    }

    public function test_invalid_transition_returns_error_and_keeps_status(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $appointment = $this->makeAppointment(
            $tenant,
            $opportunity,
            $user,
            OpportunityAppointment::STATUS_COMPLETED,
            ['outcome_notes' => 'Already done.']
        );

        $response = $this->actingAs($user)
            ->post(route('operator.crm.appointments.complete', $appointment->id), [
                'outcome_notes' => 'Attempt second completion.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $appointment->refresh();
        $this->assertSame(OpportunityAppointment::STATUS_COMPLETED, $appointment->status);
        $this->assertSame('Already done.', $appointment->outcome_notes);
    }

    public function test_cross_tenant_appointment_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        ['user' => $userA, 'opportunity' => $opportunity] = $this->makeOpportunity($tenantA);
        $appointment = $this->makeAppointment($tenantA, $opportunity, $userA);

        $tenantB = Tenant::factory()->create();
        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view', 'crm.manage']);

        $this->actingAs($userB)
            ->post(route('operator.crm.appointments.cancel', $appointment->id), [
                'outcome_notes' => 'No access.',
            ])
            ->assertNotFound();
    }

    public function test_store_rejects_assigned_to_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        ['user' => $userA, 'opportunity' => $opportunity] = $this->makeOpportunity($tenantA);

        $tenantB = Tenant::factory()->create();
        $foreignAssignee = $this->createTenantUser($tenantB, ['name' => 'Foreign Assignee'], ['sales'], ['crm.view', 'crm.manage']);

        $response = $this->actingAs($userA)
            ->from(route('operator.crm.opportunities.show', $opportunity->id))
            ->post(route('operator.crm.opportunities.appointments.store', $opportunity->id), [
                'type' => OpportunityAppointment::TYPE_CONSULTATION,
                'scheduled_at' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
                'assigned_to' => (string) $foreignAssignee->id,
            ]);

        $response->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));
        $response->assertSessionHasErrors(['assigned_to']);

        $this->assertSame(0, OpportunityAppointment::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->count());
    }

    public function test_team_member_without_manage_cannot_mutate_appointments(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createTenantUser($tenant, [], ['team_member'], ['crm.view']);
        ['opportunity' => $opportunity] = $this->makeOpportunity($tenant, $viewer);

        $this->actingAs($viewer)
            ->post(route('operator.crm.opportunities.appointments.store', $opportunity->id), [
                'type' => OpportunityAppointment::TYPE_CONSULTATION,
                'scheduled_at' => Carbon::now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(302);
    }

    public function test_opportunity_show_renders_appointment_card_with_expected_labels_and_actions(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $assignee = $this->createTenantUser($tenant, ['name' => 'Appointment Assignee'], ['sales'], ['crm.view', 'crm.manage']);

        $scheduled = $this->makeAppointment($tenant, $opportunity, $user, OpportunityAppointment::STATUS_SCHEDULED, [
            'type' => OpportunityAppointment::TYPE_CONSULTATION,
            'assigned_to' => (string) $assignee->id,
        ]);

        $completed = $this->makeAppointment($tenant, $opportunity, $user, OpportunityAppointment::STATUS_COMPLETED, [
            'type' => OpportunityAppointment::TYPE_SURVEY,
            'scheduled_at' => Carbon::now()->addDays(2),
            'outcome_notes' => 'Survey completed with site constraints noted.',
            'assigned_to' => (string) $assignee->id,
        ]);

        $siteVisit = $this->makeAppointment($tenant, $opportunity, $user, OpportunityAppointment::STATUS_SCHEDULED, [
            'type' => OpportunityAppointment::TYPE_SITE_VISIT,
            'scheduled_at' => Carbon::now()->addDays(3),
            'assigned_to' => (string) $assignee->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id));

        $response->assertOk();
        $response->assertSeeText('Lịch hẹn');
        $response->assertSeeText('Tư vấn');
        $response->assertSeeText('Khảo sát');
        $response->assertDontSee('>consultation<', false);
        $response->assertDontSee('>survey<', false);
        $response->assertSeeText('Tham quan');
        $response->assertDontSee('>site_visit<', false);
        $response->assertSeeText('Hoàn thành');
        $response->assertSeeText('Hủy');
        $response->assertSeeText('Dời lịch');
        $response->assertSeeText('Survey completed with site constraints noted.');
        $response->assertSee(route('operator.crm.appointments.complete', $scheduled->id), false);
        $response->assertSee(route('operator.crm.appointments.cancel', $scheduled->id), false);
        $response->assertSee(route('operator.crm.appointments.reschedule', $scheduled->id), false);
        $response->assertDontSee(route('operator.crm.appointments.complete', $completed->id), false);
        $response->assertDontSee(route('operator.crm.appointments.cancel', $completed->id), false);
        $response->assertDontSee(route('operator.crm.appointments.reschedule', $completed->id), false);
    }
}

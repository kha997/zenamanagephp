<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityServiceLine;
use App\Models\Permission;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * GAP-048 design §18/§19 CONCURRENCY-1/2/3 — proves the Owner-identified
 * check-then-act race is closed by real MySQL row-level locking, not just
 * by each operation's own in-isolation predicate check.
 *
 * This MUST run against a real MySQL connection with two genuinely
 * independent OS processes/PDO connections racing against each other.
 * Sequential in-process calls against sqlite (the default test DB) cannot
 * exercise cross-connection row locking and would be a false proof — see
 * the equivalent, established pattern in
 * tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php. If the
 * `mysql` connection defined in config/database.php is not reachable in
 * this environment, all three tests below skip themselves with an explicit
 * message rather than silently passing on sqlite.
 *
 * @group stress
 */
class OpportunityServiceLineConcurrencyTest extends TestCase
{
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'dependency: real MySQL connection required to prove real row-locking behavior, '
                . 'not sqlite. The "mysql" connection in config/database.php is not reachable in '
                . 'this environment (' . $e->getMessage() . '). Run this suite in an environment '
                . 'with MySQL configured before treating GAP-048 concurrency as verified — a passing '
                . 'sqlite/sequential-call test is NOT evidence.'
            );
        }
    }

    protected function tearDown(): void
    {
        try {
            if (DB::connection('mysql')->getPdo()) {
                DB::connection('mysql')->table('opportunity_service_lines')->delete();
                DB::connection('mysql')->table('quote_line_items')->delete();
                DB::connection('mysql')->table('quotes')->delete();
                DB::connection('mysql')->table('opportunities')->delete();
                DB::connection('mysql')->table('accounts')->delete();
                DB::connection('mysql')->table('user_roles')->delete();
                DB::connection('mysql')->table('role_permissions')->delete();
                DB::connection('mysql')->table('roles')->where('name', 'gap048-concurrency-actor')->delete();
                DB::connection('mysql')->table('permissions')->whereIn('name', ['crm.view', 'crm.manage', 'crm.convert'])->delete();
                DB::connection('mysql')->table('tenants')->delete();
                DB::connection('mysql')->table('users')->delete();
            }
        } catch (\Throwable $e) {
            // MySQL not reachable in this environment — nothing to clean up.
        }
        parent::tearDown();
    }

    private function makeTenantAndActor(): array
    {
        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $actor = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));

        // Grant crm.manage/crm.view/crm.convert on the mysql connection so
        // the real production authorization checks (OpportunityPolicy,
        // Gate::forUser()->authorize()) pass for the concurrency-test
        // subprocess actor, exactly as an operator user would have.
        $role = Role::on('mysql')->firstOrCreate(
            ['name' => 'gap048-concurrency-actor'],
            ['scope' => 'system', 'description' => 'GAP-048 concurrency test role', 'is_active' => true]
        );
        foreach (['crm.view', 'crm.manage', 'crm.convert'] as $permissionCode) {
            [$module, $action] = array_pad(explode('.', $permissionCode), 2, '*');
            $permission = Permission::on('mysql')->firstOrCreate(
                ['name' => $permissionCode],
                ['code' => $permissionCode, 'module' => $module, 'action' => $action, 'description' => $permissionCode]
            );
            $role->setConnection('mysql')->permissions()->syncWithoutDetaching($permission->id);
        }
        $actor->setConnection('mysql')->roles()->attach($role->id);

        return [$tenant, $actor];
    }

    private function makeOpportunityWithConfirmedDesign(Tenant $tenant, User $actor, string $stage): Opportunity
    {
        $account = Account::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Concurrency fixture account',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $opportunity = Opportunity::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Concurrency fixture',
            'pipeline_stage' => $stage,
            'created_by' => (string) $actor->id,
        ]);

        OpportunityServiceLine::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => $opportunity->id,
            'service_line' => ServiceLine::DESIGN,
            'provenance' => ServiceLineProvenance::CONFIRMED,
        ]);

        return $opportunity;
    }

    /**
     * CONCURRENCY-1: pre-scope Opportunity {DESIGN/CONFIRMED}. Race (a)
     * classification reconciliation toward {} versus (b) gated transition
     * into scope_defined. Required outcome: it must be impossible for the
     * final committed state to be scope_defined + zero CONFIRMED.
     */
    public function test_concurrency_1_reconcile_to_empty_races_transition_to_scope_defined(): void
    {
        $this->skipUnlessMysqlAvailable();

        [$tenant, $actor] = $this->makeTenantAndActor();
        $opportunity = $this->makeOpportunityWithConfirmedDesign($tenant, $actor, Opportunity::STAGE_SURVEY_OR_INPUTS_RECEIVED);

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process(
            [$php, 'artisan', 'opportunity:concurrency-test-transition', $opportunity->id, $actor->id, Opportunity::STAGE_SCOPE_DEFINED],
            base_path(),
            ['DB_CONNECTION' => 'mysql']
        );
        $procB = new Process(
            [$php, 'artisan', 'opportunity:concurrency-test-reconcile', $opportunity->id, $actor->id],
            base_path(),
            ['DB_CONNECTION' => 'mysql']
        );

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $fresh = Opportunity::on('mysql')->find($opportunity->id);
        $confirmedCount = OpportunityServiceLine::on('mysql')
            ->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)
            ->count();

        $this->assertFalse(
            $fresh->pipeline_stage === Opportunity::STAGE_SCOPE_DEFINED && $confirmedCount === 0,
            'Illegal state reached: scope_defined with zero CONFIRMED. '
            . 'A(' . $procA->getExitCode() . '): ' . $procA->getOutput() . $procA->getErrorOutput()
            . ' | B(' . $procB->getExitCode() . '): ' . $procB->getOutput() . $procB->getErrorOutput()
        );

        // At least one of the two operations must have observed the
        // serialized post-lock state and rejected — a race that let BOTH
        // silently succeed at their own initial intent (rather than one
        // being genuinely serialized behind the other) would also be a
        // proof failure even if the final state happens to look legal by
        // accident. Exactly one of the following must be true:
        // either the transition landed in scope_defined (meaning it
        // observed >=1 CONFIRMED, i.e. it ran BEFORE or the reconcile
        // observed the gated stage and rejected), or it did not (meaning
        // it observed the pre-scope stage but reconcile still had not
        // reached zero, or the transition itself lost the race and the
        // final state reflects reconcile winning while transition's own
        // gate check (post-lock) correctly saw zero CONFIRMED and
        // rejected).
        $this->assertNotSame(
            [0, 0],
            [$procA->getExitCode(), $procB->getExitCode()],
            'At least one of the two racing operations must be affected by serialization '
            . '(reject or observe the other\'s committed state) — both cannot be free-running.'
        );
    }

    /**
     * CONCURRENCY-2: DRAFT Quote, Opportunity {DESIGN/CONFIRMED}. Race (a)
     * classification reconciliation toward {} versus (b) sendQuote().
     * Required outcome: it must be impossible for the final committed state
     * to be Quote SENT + zero CONFIRMED.
     */
    public function test_concurrency_2_reconcile_to_empty_races_send_quote(): void
    {
        $this->skipUnlessMysqlAvailable();

        [$tenant, $actor] = $this->makeTenantAndActor();
        $opportunity = $this->makeOpportunityWithConfirmedDesign($tenant, $actor, Opportunity::STAGE_NEW_LEAD);

        $quote = Quote::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => 'BG-CONC-0001',
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 0,
            'created_by' => (string) $actor->id,
        ]);
        QuoteLineItem::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 1,
            'name' => 'Line A',
            'unit' => 'pcs',
            'quantity' => 1,
            'unit_price' => 100000,
            'amount' => 100000,
        ]);

        $php = (new PhpExecutableFinder())->find();
        $procA = new Process(
            [$php, 'artisan', 'opportunity:concurrency-test-send-quote', $quote->id, $actor->id],
            base_path(),
            ['DB_CONNECTION' => 'mysql']
        );
        $procB = new Process(
            [$php, 'artisan', 'opportunity:concurrency-test-reconcile', $opportunity->id, $actor->id],
            base_path(),
            ['DB_CONNECTION' => 'mysql']
        );

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $freshQuote = Quote::on('mysql')->find($quote->id);
        $confirmedCount = OpportunityServiceLine::on('mysql')
            ->where('opportunity_id', $opportunity->id)
            ->where('provenance', ServiceLineProvenance::CONFIRMED)
            ->count();

        $this->assertFalse(
            $freshQuote->status === Quote::STATUS_SENT && $confirmedCount === 0,
            'Illegal state reached: Quote SENT with zero CONFIRMED. '
            . 'A(' . $procA->getExitCode() . '): ' . $procA->getOutput() . $procA->getErrorOutput()
            . ' | B(' . $procB->getExitCode() . '): ' . $procB->getOutput() . $procB->getErrorOutput()
        );
    }

    /**
     * CONCURRENCY-3: a legacy service_category update() whose mapper-owned
     * INFERRED reconciliation step fails (simulated constraint violation):
     * required proof that the scalar update itself rolls back too — no
     * partially-applied state.
     */
    public function test_concurrency_3_legacy_update_rolls_back_scalar_when_mapper_reconciliation_fails(): void
    {
        $this->skipUnlessMysqlAvailable();

        [$tenant, $actor] = $this->makeTenantAndActor();
        $account = Account::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'CONCURRENCY-3 account',
            'status' => Account::STATUS_ACTIVE,
        ]);
        $opportunity = Opportunity::on('mysql')->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'CONCURRENCY-3 fixture',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'created_by' => (string) $actor->id,
        ]);

        $php = (new PhpExecutableFinder())->find();
        $proc = new Process(
            [$php, 'artisan', 'opportunity:concurrency-test-update-category', $opportunity->id, 'construction', $actor->id, '--fail-mapper-write'],
            base_path(),
            ['DB_CONNECTION' => 'mysql']
        );
        $proc->run();

        $this->assertSame(1, $proc->getExitCode(), 'The simulated mapper failure must surface as a non-zero exit: ' . $proc->getOutput() . $proc->getErrorOutput());

        $fresh = Opportunity::on('mysql')->find($opportunity->id);
        $this->assertSame('architecture', $fresh->service_category, 'Scalar must roll back together with the failed canonical reconciliation, no partial state.');
        $this->assertSame(
            0,
            OpportunityServiceLine::on('mysql')->where('opportunity_id', $opportunity->id)->where('service_line', ServiceLine::CONSTRUCTION)->count()
        );
    }
}

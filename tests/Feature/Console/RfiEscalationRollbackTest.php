<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RfiEscalationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_clears_cutover_flag_without_touching_escalation_tables(): void
    {
        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('rfi:escalation-rollback', ['--reason' => 'Reverting after an unrelated production incident, needs investigation'])
            ->assertExitCode(0);

        $stillCutover = DB::table('rfi_escalation_migration_state')->whereNotNull('cutover_completed_at')->exists();
        $this->assertFalse($stillCutover);

        // The rfi_escalations and rfi_legacy_migration_confirmations TABLES themselves are never
        // touched by this command — it only ever writes to rfi_escalation_migration_state.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('rfi_escalations'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('rfi_legacy_migration_confirmations'));
    }

    public function test_rollback_requires_a_reason(): void
    {
        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('rfi:escalation-rollback')->assertExitCode(1);
    }
}

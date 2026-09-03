<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstraps_real_tenant_and_admin_on_empty_database(): void
    {
        $this->assertSame(0, Tenant::count());

        $exitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Acme Construction',
            '--tenant-slug' => 'acme-construction',
            '--admin-email' => 'owner@acme-construction.example',
        ])->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame(1, Tenant::count());
        $this->assertSame('Acme Construction', Tenant::first()->name);

        $admin = User::where('email', 'owner@acme-construction.example')->first();
        $this->assertNotNull($admin);
        $this->assertSame(Tenant::first()->id, $admin->tenant_id);
        $this->assertTrue($admin->is_active);
    }

    public function test_never_creates_fixed_or_default_password(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Beta Co',
            '--tenant-slug' => 'beta-co',
            '--admin-email' => 'owner@beta-co.example',
        ])->run();

        $admin = User::where('email', 'owner@beta-co.example')->first();

        foreach (['password', 'zena1234', 'Password123', 'changeme', ''] as $forbidden) {
            $this->assertFalse(
                Hash::check($forbidden, $admin->password),
                "Bootstrap admin password must never equal a known fixed/default value ({$forbidden})."
            );
        }
    }

    public function test_never_invokes_database_seeder(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ProductionBootstrapCommand.php'));
        $this->assertStringNotContainsString('DatabaseSeeder', $source);
        $this->assertStringNotContainsString('db:seed', $source);
        $this->assertStringNotContainsString("Artisan::call('migrate:fresh --seed')", $source);
    }

    public function test_no_demo_tenant_or_user_created(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Gamma LLC',
            '--tenant-slug' => 'gamma-llc',
            '--admin-email' => 'owner@gamma-llc.example',
        ])->run();

        $this->assertSame(0, User::where('email', 'like', '%demo%')->count());
        $this->assertSame(0, Tenant::where('slug', 'like', '%demo%')->count());
        $this->assertSame(1, User::count(), 'Bootstrap must create exactly one user — no extra demo accounts.');
    }

    public function test_second_bootstrap_fails_closed_idempotent(): void
    {
        $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Delta Inc',
            '--tenant-slug' => 'delta-inc',
            '--admin-email' => 'owner@delta-inc.example',
        ])->run();

        $this->assertSame(1, Tenant::count());

        $secondExitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Delta Inc Again',
            '--tenant-slug' => 'delta-inc-again',
            '--admin-email' => 'other@delta-inc.example',
        ])->run();

        $this->assertSame(2, $secondExitCode);
        $this->assertSame(1, Tenant::count(), 'A second bootstrap attempt must never create a second tenant.');
        $this->assertSame(1, User::count());
    }

    public function test_existing_data_path_declines_without_seeding(): void
    {
        Tenant::factory()->create(['name' => 'Pre-existing Real Co']);
        $preExistingCount = Tenant::count();

        $exitCode = $this->artisan('production:bootstrap', [
            '--tenant-name' => 'Should Not Be Created',
            '--tenant-slug' => 'should-not-be-created',
            '--admin-email' => 'nope@example.com',
        ])->run();

        $this->assertSame(1, $exitCode);
        $this->assertSame($preExistingCount, Tenant::count());
        $this->assertSame(0, Tenant::where('name', 'Should Not Be Created')->count());
    }
}

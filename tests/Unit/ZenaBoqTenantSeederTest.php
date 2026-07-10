<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use Database\Seeders\ZenaBoqTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenaBoqTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_zena_tenant(): void
    {
        $this->assertSame(0, Tenant::where('name', 'Z.E.N.A')->count());

        (new ZenaBoqTenantSeeder())->run();

        $this->assertSame(1, Tenant::where('name', 'Z.E.N.A')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        (new ZenaBoqTenantSeeder())->run();
        $firstId = Tenant::where('name', 'Z.E.N.A')->value('id');

        (new ZenaBoqTenantSeeder())->run();

        $this->assertSame(1, Tenant::where('name', 'Z.E.N.A')->count());
        $this->assertSame($firstId, Tenant::where('name', 'Z.E.N.A')->value('id'));
    }

    public function test_config_defaults_resolve(): void
    {
        $this->assertSame('Z.E.N.A', config('zena_boq.integration_tenant_name'));
    }
}
